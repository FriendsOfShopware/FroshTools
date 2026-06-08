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
        };
    },

    created() {
        this.language =
            Shopware.Application.getContainer(
                'factory'
            ).locale.getLastKnownLocale();
        const language = this.language;
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

        largestTableSize() {
            if (!this.dbStats || !this.dbStats.tables) {
                return 0;
            }

            return this.dbStats.tables.reduce(
                (max, table) => Math.max(max, table.totalSize || 0),
                0
            );
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
                this.cacheStats =
                    await this.froshToolsService.getCacheStatistics();
            } catch (e) {
                this.cacheStats = null;
                console.error(
                    '[frosh-tools] failed to load cache statistics',
                    e
                );
            }
            this.isLoadingCache = false;
        },

        async loadDbStats() {
            this.isLoadingDb = true;
            try {
                this.dbStats =
                    await this.froshToolsService.getDatabaseStatistics();
            } catch (e) {
                this.dbStats = null;
                console.error(
                    '[frosh-tools] failed to load database statistics',
                    e
                );
            }
            this.isLoadingDb = false;
        },

        formatSize(bytes) {
            if (bytes >= 1024 * 1024 * 1024) {
                return (
                    this.percentFormatter.format(bytes / (1024 * 1024 * 1024)) +
                    ' GiB'
                );
            }

            if (bytes >= 1024 * 1024) {
                return (
                    this.percentFormatter.format(bytes / (1024 * 1024)) + ' MiB'
                );
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

        formatDecimal(value) {
            return this.percentFormatter.format(value);
        },

        formatDateTime(value) {
            if (!value) {
                return '';
            }

            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleString(this.language);
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

        // Clamp a 0-100 value to a safe meter width.
        clampPercent(value) {
            if (typeof value !== 'number' || Number.isNaN(value)) {
                return 0;
            }

            return Math.min(100, Math.max(0, value));
        },

        // used / total as a 0-100 percentage; returns 0 when total is unknown.
        ratioPercent(used, total) {
            if (!total || total <= 0) {
                return 0;
            }

            return this.clampPercent((used / total) * 100);
        },

        // Variant for a fill meter: higher fill = more pressure (inverse of hit rate).
        fillVariant(percent) {
            if (percent >= 90) return 'danger';
            if (percent >= 75) return 'warning';
            return 'success';
        },

        tableSizeWidth(size) {
            return this.ratioPercent(size, this.largestTableSize);
        },
    },
});
