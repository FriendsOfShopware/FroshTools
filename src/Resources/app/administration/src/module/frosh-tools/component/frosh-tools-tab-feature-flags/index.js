import template from './template.html.twig';
import './style.scss';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-feature-flags', {
    template,
    inject: ['froshToolsService'],
    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('frosh-sortable-table'),
    ],

    data() {
        return {
            featureFlags: null,
            isLoading: true,
            loadError: null,
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async refresh() {
            await this.createdComponent();
        },

        async createdComponent() {
            this.isLoading = true;
            this.loadError = null;

            try {
                this.featureFlags =
                    await this.froshToolsService.getFeatureFlags();
            } catch (error) {
                this.featureFlags = null;
                this.loadError = error?.response?.data?.error ?? error.message;
                this.createNotificationError({ message: this.loadError });
            } finally {
                this.isLoading = false;
            }
        },
    },
});
