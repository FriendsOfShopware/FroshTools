import template from './template.twig';
import recommendations from './recommendations';
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
            showDone: false,
            loadError: null,
            health: null,
            performanceStatus: [],
            activeInfo: null,
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        filteredPerformanceStatus() {
            if (this.showDone) {
                return this.performanceStatus;
            }

            return this.performanceStatus.filter(
                (item) => item.state !== 'STATE_OK'
            );
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
