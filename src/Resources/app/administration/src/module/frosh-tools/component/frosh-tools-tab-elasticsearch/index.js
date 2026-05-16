import template from './template.twig';

const { Mixin, Component } = Shopware;

Component.register('frosh-tools-tab-elasticsearch', {
    template,

    inject: ['froshElasticSearch'],
    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: true,
            isActive: true,
            statusInfo: {},
            indices: [],
            consoleInput: 'GET /_cat/indices',
            consoleOutput: {},
        };
    },

    computed: {
        columns() {
            return [
                {
                    property: 'name',
                    label: 'frosh-tools.name',
                    rawData: true,
                    primary: true,
                },
                {
                    property: 'indexSize',
                    label: 'frosh-tools.size',
                    rawData: true,
                    primary: true,
                },
                {
                    property: 'docs',
                    label: 'frosh-tools.docs',
                    rawData: true,
                    primary: true,
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
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

            if (Math.abs(bytes) < thresh) {
                return bytes + ' B';
            }

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
                    message: this.$t('frosh-tools.tabs.elasticsearch.notification.cleanup.empty'),
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
                        message: this.$t('frosh-tools.tabs.elasticsearch.notification.cleanup.empty'),
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

        async cleanupOrphaned() {
            let preview;
            try {
                preview = await this.froshElasticSearch.getOrphanedIndices();
            } catch (e) {
                this.createNotificationError({
                    message: e?.message ?? this.$t('global.default.error'),
                });
                return;
            }

            const indices = preview?.indices ?? [];
            const previewError = preview?.error ?? null;

            if (previewError) {
                this.createNotificationError({ message: previewError });
                return;
            }

            if (indices.length === 0) {
                this.createNotificationInfo({
                    message: this.$t('frosh-tools.tabs.elasticsearch.notification.orphaned.empty'),
                });
                return;
            }

            const confirmMessage = this.$t(
                'frosh-tools.tabs.elasticsearch.notification.orphaned.confirm',
                { count: indices.length, list: indices.join('\n') }
            );

            // eslint-disable-next-line no-alert
            if (!window.confirm(confirmMessage)) {
                return;
            }

            try {
                const result = await this.froshElasticSearch.cleanupOrphaned();
                this.notifyCleanupResult(result);
            } catch (e) {
                this.createNotificationError({
                    message: e?.message ?? this.$t('global.default.error'),
                });
            }

            await this.createdComponent();
        },
    },
});
