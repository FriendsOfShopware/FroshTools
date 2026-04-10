import template from './template.twig';
import './style.scss';

const { Component } = Shopware;

Component.register('frosh-tools-tab-statistics', {
    template,

    inject: ['froshToolsService'],

    data() {
        return {
            cacheStats: null,
            dbStats: null,
            isLoadingCache: true,
            isLoadingDb: true,
            numberFormatter: null,
            percentFormatter: null,
        };
    },

    created() {
        const language =
            Shopware.Application.getContainer(
                'factory'
            ).locale.getLastKnownLocale();
        this.numberFormatter = new Intl.NumberFormat(language, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        });
        this.percentFormatter = new Intl.NumberFormat(language, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        this.loadData();
    },

    computed: {
        isLoading() {
            return this.isLoadingCache || this.isLoadingDb;
        },

        tableColumns() {
            return [
                {
                    property: 'name',
                    label: this.$t('frosh-tools.tabs.statistics.tableName'),
                    rawData: true,
                    allowResize: true,
                },
                {
                    property: 'engine',
                    label: this.$t('frosh-tools.tabs.statistics.engine'),
                    rawData: true,
                    allowResize: true,
                },
                {
                    property: 'rows',
                    label: this.$t('frosh-tools.tabs.statistics.rows'),
                    rawData: true,
                    align: 'right',
                    allowResize: true,
                },
                {
                    property: 'dataSize',
                    label: this.$t('frosh-tools.tabs.statistics.dataSize'),
                    rawData: true,
                    align: 'right',
                    allowResize: true,
                },
                {
                    property: 'indexSize',
                    label: this.$t('frosh-tools.tabs.statistics.indexSize'),
                    rawData: true,
                    align: 'right',
                    allowResize: true,
                },
                {
                    property: 'totalSize',
                    label: this.$t('frosh-tools.tabs.statistics.totalSize'),
                    rawData: true,
                    align: 'right',
                    allowResize: true,
                },
            ];
        },
    },

    methods: {
        loadData() {
            this.loadCacheStats();
            this.loadDbStats();
        },

        async loadCacheStats() {
            this.isLoadingCache = true;
            try {
                this.cacheStats = await this.froshToolsService.getCacheStatistics();
            } catch {
                this.cacheStats = null;
            }
            this.isLoadingCache = false;
        },

        async loadDbStats() {
            this.isLoadingDb = true;
            try {
                this.dbStats = await this.froshToolsService.getDatabaseStatistics();
            } catch {
                this.dbStats = null;
            }
            this.isLoadingDb = false;
        },

        formatSize(bytes) {
            if (bytes >= 1024 * 1024 * 1024) {
                return this.percentFormatter.format(bytes / (1024 * 1024 * 1024)) + ' GiB';
            }

            if (bytes >= 1024 * 1024) {
                return this.percentFormatter.format(bytes / (1024 * 1024)) + ' MiB';
            }

            if (bytes >= 1024) {
                return this.percentFormatter.format(bytes / 1024) + ' KiB';
            }

            return this.numberFormatter.format(bytes) + ' B';
        },

        formatNumber(number) {
            return this.numberFormatter.format(number);
        },

        formatPercent(value) {
            return this.percentFormatter.format(value) + ' %';
        },

        formatUptime(seconds) {
            const days = Math.floor(seconds / 86400);
            const hours = Math.floor((seconds % 86400) / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);

            if (days > 0) {
                return `${days}d ${hours}h ${minutes}m`;
            }

            if (hours > 0) {
                return `${hours}h ${minutes}m`;
            }

            return `${minutes}m`;
        },

        hitRateVariant(rate) {
            if (rate >= 95) return 'success';
            if (rate >= 80) return 'warning';
            return 'danger';
        },
    },
});
