import template from './template.twig';
import recommendations from './recommendations';
import './style.scss';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-index', {
    inject: ['froshToolsService'],
    mixins: [Mixin.getByName('frosh-sortable-table')],
    template,

    data() {
        return {
            isLoading: true,
            health: null,
            performanceStatus: null,
            activeInfo: null,
        };
    },

    created() {
        this.createdComponent();
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
            this.isLoading = true;
            await this.createdComponent();
        },

        async createdComponent() {
            this.health = await this.froshToolsService.healthStatus();
            this.performanceStatus =
                await this.froshToolsService.performanceStatus();
            this.isLoading = false;
        },
    },
});
