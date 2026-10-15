import { resolveSection } from '../../section-navigation.ts';
import template from './template.twig';
import './frosh-tools-tab-fastly.scss';
import { PRIVILEGE } from '../../acl/privileges.js';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-fastly', {
    template,
    inject: ['froshToolsService', 'acl'],
    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('frosh-sortable-table'),
    ],

    data() {
        return {
            purgePath: '',
            isLoading: false,
            stats: null,
            statsLoading: true,
            snippetsLoading: true,
            statsError: null,
            snippetsError: null,
            snippets: [],
            activeSnippet: null,
            timeframe: '2h',
            numberFormater: null,
        };
    },

    computed: {
        sectionLoading() {
            return this.activeSection === 'snippets'
                ? this.snippetsLoading
                : this.statsLoading;
        },
        sectionError() {
            if (this.activeSection === 'traffic') return this.statsError;
            if (this.activeSection === 'snippets') return this.snippetsError;
            return null;
        },

        sections() {
            return ['traffic', 'purge', 'snippets']
                .filter((key) => this.canUpdate || !['purge'].includes(key))
                .map((key) => ({
                    key,
                    label: this.$t(`frosh-tools.tabs.fastly.sections.${key}`),
                }));
        },

        activeSection() {
            return resolveSection(this.$route?.query?.section, this.sections);
        },

        timeframeOptions() {
            return [
                {
                    value: '30m',
                    label: this.$t('frosh-tools.tabs.fastly.timeframes.30m'),
                },
                {
                    value: '1h',
                    label: this.$t('frosh-tools.tabs.fastly.timeframes.1h'),
                },
                {
                    value: '2h',
                    label: this.$t('frosh-tools.tabs.fastly.timeframes.2h'),
                },
                {
                    value: '24h',
                    label: this.$t('frosh-tools.tabs.fastly.timeframes.24h'),
                },
                {
                    value: '7d',
                    label: this.$t('frosh-tools.tabs.fastly.timeframes.7d'),
                },
                {
                    value: '30d',
                    label: this.$t('frosh-tools.tabs.fastly.timeframes.30d'),
                },
            ];
        },

        canUpdate() {
            return this.acl.can(PRIVILEGE.FASTLY_UPDATE);
        },
    },

    watch: {
        activeSnippet(value) {
            if (value) {
                this.resizeCodeEditor('snippetEditor');
            }
        },
    },

    created() {
        const language =
            Shopware.Application.getContainer(
                'factory'
            ).locale.getLastKnownLocale();
        this.numberFormater = new Intl.NumberFormat(language, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
        this.loadStats();
        this.loadSnippets();
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

        async refreshSection() {
            if (this.activeSection === 'snippets') await this.loadSnippets();
            else await this.loadStats();
        },

        async loadStats() {
            this.statsLoading = true;
            this.statsError = null;
            try {
                this.stats = await this.froshToolsService.getFastlyStatistics(
                    this.timeframe
                );
            } catch (error) {
                this.statsError =
                    error?.message || this.$t('frosh-tools.loadError.title');
            } finally {
                this.statsLoading = false;
            }
        },

        async loadSnippets() {
            this.snippetsLoading = true;
            this.snippetsError = null;
            try {
                this.snippets =
                    await this.froshToolsService.getFastlySnippets();
            } catch (error) {
                this.snippetsError =
                    error?.message || this.$t('frosh-tools.loadError.title');
            } finally {
                this.snippetsLoading = false;
            }
        },

        formatSize(bytes) {
            if (bytes > 1024 * 1024 * 1024) {
                const formatted = bytes / (1024 * 1024 * 1024);
                return this.numberFormater.format(formatted) + ' GiB';
            }
            const formatted = bytes / (1024 * 1024);
            return this.numberFormater.format(formatted) + ' MiB';
        },

        formatNumber(number) {
            return this.numberFormater.format(number);
        },

        async onPurgeAll() {
            this.isLoading = true;
            try {
                await this.froshToolsService.fastlyPurgeAll();
                this.createNotificationSuccess({
                    message: this.$t('frosh-tools.tabs.fastly.purgeAllSuccess'),
                });
            } catch {
                this.createNotificationError({
                    message: this.$t('frosh-tools.tabs.fastly.purgeAllError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        async onPurge() {
            if (!this.purgePath) return;
            this.isLoading = true;
            try {
                await this.froshToolsService.fastlyPurge(this.purgePath);
                this.createNotificationSuccess({
                    message: this.$t('frosh-tools.tabs.fastly.purgeSuccess'),
                });
                this.purgePath = '';
            } catch {
                this.createNotificationError({
                    message: this.$t('frosh-tools.tabs.fastly.purgeError'),
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
});
