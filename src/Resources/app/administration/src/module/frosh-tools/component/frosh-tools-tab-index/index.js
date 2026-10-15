import template from './template.twig';
import recommendations from './recommendations.js';
import './style.scss';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-index', {
    inject: ['froshToolsService'],
    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('frosh-sortable-table'),
        Mixin.getByName('frosh-settings-result'),
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
