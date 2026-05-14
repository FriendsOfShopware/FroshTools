import template from './template.twig';
import './style.scss';

const { Component } = Shopware;

Component.register('frosh-tools-tab-index', {
    inject: ['froshToolsService'],
    template,

    data() {
        return {
            isLoading: true,
            health: null,
            performanceStatus: null,
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        errorCount() {
            return this.countByState('STATE_ERROR', this.health);
        },
        warningCount() {
            return this.countByState('STATE_WARNING', this.health);
        },
        infoCount() {
            return this.countByState('STATE_INFO', this.health);
        },
        recommendationCount() {
            return this.performanceStatus ? this.performanceStatus.length : 0;
        },
    },

    methods: {
        countByState(state, source) {
            if (!source) return 0;
            return source.filter((i) => i.state === state).length;
        },

        pillClass(state) {
            switch (state) {
                case 'STATE_ERROR':   return 'ft-pill--danger';
                case 'STATE_WARNING': return 'ft-pill--warning';
                case 'STATE_INFO':    return 'ft-pill--info';
                default:              return 'ft-pill--success';
            }
        },

        stateLabel(state) {
            switch (state) {
                case 'STATE_ERROR':   return this.$t('frosh-tools.error');
                case 'STATE_WARNING': return this.$t('frosh-tools.warning');
                case 'STATE_INFO':    return this.$t('frosh-tools.info');
                default:              return this.$t('frosh-tools.good');
            }
        },

        async refresh() {
            this.isLoading = true;
            await this.createdComponent();
        },

        async createdComponent() {
            this.health = await this.froshToolsService.healthStatus();
            this.performanceStatus = await this.froshToolsService.performanceStatus();
            this.isLoading = false;
        },
    },
});
