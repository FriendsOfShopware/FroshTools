import template from './template.twig';
import recommendations from './recommendations.js';
import './style.scss';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-index', {
    inject: ['froshToolsService'],
    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('frosh-sortable-table'),
    ],
    template,

    data() {
        return {
            isLoading: true,
            loadError: null,
            health: null,
            performanceStatus: null,
            activeInfo: null,
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        checkSummary() {
            if (this.isLoading || this.loadError) return null;
            const checks = [
                ...(this.health || []),
                ...(this.performanceStatus || []),
            ];
            if (!checks.length) return null;

            const errors = checks.filter(
                (check) => check.state === 'STATE_ERROR'
            ).length;
            const warnings = checks.filter(
                (check) => check.state === 'STATE_WARNING'
            ).length;
            const variant = errors ? 'danger' : warnings ? 'warning' : 'info';

            return {
                variant,
                icon: errors || warnings ? 'alert' : 'activity',
                title: this.$t(
                    `frosh-tools.tabs.index.summary.${errors || warnings ? 'attention' : 'reviewed'}`
                ),
                body: this.$t('frosh-tools.tabs.index.summary.detail', {
                    total: checks.length,
                    errors,
                    warnings,
                }),
            };
        },
    },

    methods: {
        recommendationFor(item) {
            return (item && recommendations[item.id]) || null;
        },

        hasInfo(item) {
            return Boolean(this.recommendationFor(item));
        },

        openInfo(item) {
            this.activeInfo = item;
        },

        closeInfo() {
            this.activeInfo = null;
        },

        pillVariant(state) {
            switch (state) {
                case 'STATE_ERROR':
                    return 'danger';
                case 'STATE_WARNING':
                    return 'warning';
                case 'STATE_INFO':
                    return 'info';
                default:
                    return 'success';
            }
        },

        stateLabel(state) {
            switch (state) {
                case 'STATE_ERROR':
                    return this.$t('frosh-tools.error');
                case 'STATE_WARNING':
                    return this.$t('frosh-tools.warning');
                case 'STATE_INFO':
                    return this.$t('frosh-tools.info');
                default:
                    return this.$t('frosh-tools.good');
            }
        },

        async refresh() {
            await this.createdComponent();
        },

        async createdComponent() {
            this.isLoading = true;
            this.loadError = null;

            try {
                [this.health, this.performanceStatus] = await Promise.all([
                    this.froshToolsService.healthStatus(),
                    this.froshToolsService.performanceStatus(),
                ]);
            } catch (error) {
                this.health = null;
                this.performanceStatus = null;
                this.loadError = error?.response?.data?.error ?? error.message;
                this.createNotificationError({ message: this.loadError });
            } finally {
                this.isLoading = false;
            }
        },
    },
});
