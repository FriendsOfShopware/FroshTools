import template from './template.twig';
import './style.scss';

const { Mixin, Component } = Shopware;

Component.register('frosh-tools-tab-elasticsearch', {
    template,

    inject: ['froshElasticSearch'],
    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('frosh-sortable-table'),
    ],

    data() {
        return {
            isLoading: true,
            isActive: true,
            statusInfo: {},
            indices: [],
            consoleInput: 'GET /_cat/indices',
            consoleOutput: {},
            showOrphanedCleanupModal: false,
            orphanedCleanupIndices: [],
            isLoadingOrphanedPreview: false,
            isCleaningOrphaned: false,
        };
    },

    computed: {
        engineName() {
            return this.statusInfo.info?.version?.distribution === 'opensearch'
                ? 'OpenSearch'
                : 'Elasticsearch';
        },

        engineVersion() {
            return this.statusInfo.info?.version?.number ?? null;
        },

        clusterHealth() {
            return this.statusInfo.health?.status ?? null;
        },

        healthVariant() {
            switch (this.clusterHealth) {
                case 'green':
                    return 'success';
                case 'yellow':
                    return 'warning';
                case 'red':
                    return 'danger';
                default:
                    return null;
            }
        },

        nodeCount() {
            return this.statusInfo.health?.number_of_nodes ?? null;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        resizeCodeEditor(refName) {
            const resize = () => {
                const editor = this.$refs[refName]?.editor;

                if (!editor || typeof editor.resize !== 'function') {
                    return;
                }

                editor.resize(true);
            };

            this.$nextTick(() => {
                resize();

                if (typeof window === 'undefined') {
                    return;
                }

                window.requestAnimationFrame(() => {
                    resize();
                    window.requestAnimationFrame(resize);
                });
                window.setTimeout(resize, 220);
            });
        },

        async createdComponent() {
            this.isLoading = true;
            try {
                this.statusInfo = await this.froshElasticSearch.status();
            } catch {
                this.isActive = false;
                this.isLoading = false;
                return;
            } finally {
                this.isLoading = false;
            }
            this.indices = await this.froshElasticSearch.indices();
        },

        formatSize(bytes) {
            const thresh = 1024;
            const dp = 1;
            let formatted = bytes;
            if (Math.abs(bytes) < thresh) return bytes + ' B';
            const units = [
                'KiB',
                'MiB',
                'GiB',
                'TiB',
                'PiB',
                'EiB',
                'ZiB',
                'YiB',
            ];
            let index = -1;
            const reach = 10 ** dp;
            do {
                formatted /= thresh;
                ++index;
            } while (
                Math.round(Math.abs(formatted) * reach) / reach >= thresh &&
                index < units.length - 1
            );
            return formatted.toFixed(dp) + ' ' + units[index];
        },

        async deleteIndex(indexName) {
            await this.froshElasticSearch.deleteIndex(indexName);
            await this.createdComponent();
        },

        async onConsoleEnter() {
            const lines = this.consoleInput.split('\n');
            const requestLine = lines.shift();
            const payload = lines.join('\n').trim();
            const [method, uri] = requestLine.split(' ');
            try {
                this.consoleOutput = await this.froshElasticSearch.console(
                    method,
                    uri,
                    payload
                );
            } catch (e) {
                this.consoleOutput = e.response.data;
            }
        },

        async reindex() {
            await this.froshElasticSearch.reindex();
            this.createNotificationSuccess({
                message: this.$t('global.default.success'),
            });
            await this.createdComponent();
        },

        async switchAlias() {
            await this.froshElasticSearch.switchAlias();
            this.createNotificationSuccess({
                message: this.$t('global.default.success'),
            });
            await this.createdComponent();
        },

        async flushAll() {
            await this.froshElasticSearch.flushAll();
            this.createNotificationSuccess({
                message: this.$t('global.default.success'),
            });
            await this.createdComponent();
        },

        async resetElasticsearch() {
            await this.froshElasticSearch.reset();
            this.createNotificationSuccess({
                message: this.$t('global.default.success'),
            });
            await this.createdComponent();
        },

        notifyCleanupResult(result) {
            const deleted = result?.deleted ?? [];
            const errors = result?.errors ?? {};
            const errorKeys = Object.keys(errors);

            if (deleted.length === 0 && errorKeys.length === 0) {
                this.createNotificationInfo({
                    message: this.$t(
                        'frosh-tools.tabs.elasticsearch.notification.cleanup.empty'
                    ),
                });
            } else if (deleted.length > 0) {
                this.createNotificationSuccess({
                    message: this.$t(
                        'frosh-tools.tabs.elasticsearch.notification.cleanup.success',
                        { count: deleted.length }
                    ),
                });
            }

            if (errorKeys.length > 0) {
                this.createNotificationError({
                    message: this.$t(
                        'frosh-tools.tabs.elasticsearch.notification.cleanup.partialError',
                        { count: errorKeys.length }
                    ),
                });
            }
        },

        async cleanup() {
            try {
                const result = await this.froshElasticSearch.cleanup();
                this.notifyCleanupResult(result);
            } catch (e) {
                this.createNotificationError({
                    message: e?.message ?? this.$t('global.default.error'),
                });
            }

            await this.createdComponent();
        },

        async showUnusedIndices() {
            try {
                const result = await this.froshElasticSearch.getUnusedIndices();
                const indices = result?.indices ?? [];
                const error = result?.error ?? null;

                if (error) {
                    this.createNotificationError({ message: error });
                    return;
                }

                if (indices.length === 0) {
                    this.createNotificationInfo({
                        message: this.$t(
                            'frosh-tools.tabs.elasticsearch.notification.cleanup.empty'
                        ),
                    });
                    return;
                }

                this.createNotificationInfo({
                    message: this.$t(
                        'frosh-tools.tabs.elasticsearch.notification.unused.list',
                        { count: indices.length, list: indices.join(', ') }
                    ),
                });
            } catch (e) {
                this.createNotificationError({
                    message: e?.message ?? this.$t('global.default.error'),
                });
            }
        },

        async openOrphanedCleanupModal() {
            this.isLoadingOrphanedPreview = true;

            let preview;
            try {
                preview = await this.froshElasticSearch.getOrphanedIndices();
            } catch (e) {
                this.createNotificationError({
                    message: e?.message ?? this.$t('global.default.error'),
                });
                return;
            } finally {
                this.isLoadingOrphanedPreview = false;
            }

            const indices = preview?.indices ?? [];
            const previewError = preview?.error ?? null;

            if (previewError) {
                this.createNotificationError({ message: previewError });
                return;
            }

            if (indices.length === 0) {
                this.createNotificationInfo({
                    message: this.$t(
                        'frosh-tools.tabs.elasticsearch.notification.orphaned.empty'
                    ),
                });
                return;
            }

            this.orphanedCleanupIndices = indices;
            this.showOrphanedCleanupModal = true;
        },

        closeOrphanedCleanupModal(force = false) {
            if (this.isCleaningOrphaned && force !== true) {
                return;
            }

            this.showOrphanedCleanupModal = false;
            this.orphanedCleanupIndices = [];
        },

        async confirmCleanupOrphaned() {
            if (this.orphanedCleanupIndices.length === 0) {
                this.closeOrphanedCleanupModal(true);
                return;
            }

            this.isCleaningOrphaned = true;
            let shouldClose = false;

            try {
                const result = await this.froshElasticSearch.cleanupOrphaned(
                    this.orphanedCleanupIndices
                );
                this.notifyCleanupResult(result);
                shouldClose = true;
            } catch (e) {
                this.createNotificationError({
                    message: e?.message ?? this.$t('global.default.error'),
                });
            } finally {
                this.isCleaningOrphaned = false;
            }

            if (shouldClose) {
                this.closeOrphanedCleanupModal(true);
                await this.createdComponent();
            }
        },
    },
});
