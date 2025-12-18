import template from './template.twig';
import './frosh-tools-tab-fastly.scss';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-fastly', {
    template,

    inject: ['froshToolsService'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            purgePath: '',
            isLoading: false,
            stats: null,
            snippets: [],
            activeSnippet: null,
            timeframe: '2h',
            numberFormater: null,
        };
    },

    computed: {
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

        snippetColumns() {
            return [
                {
                    property: 'name',
                    label: this.$t('frosh-tools.tabs.fastly.snippets.name'),
                    allowResize: true,
                },
                {
                    property: 'type',
                    label: this.$t('frosh-tools.tabs.fastly.snippets.type'),
                    allowResize: true,
                },
                {
                    property: 'priority',
                    label: this.$t('frosh-tools.tabs.fastly.snippets.priority'),
                    allowResize: true,
                },
            ];
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
        async loadStats() {
            this.stats = await this.froshToolsService.getFastlyStatistics(
                this.timeframe
            );
        },

        async loadSnippets() {
            this.snippets = await this.froshToolsService.getFastlySnippets();
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
