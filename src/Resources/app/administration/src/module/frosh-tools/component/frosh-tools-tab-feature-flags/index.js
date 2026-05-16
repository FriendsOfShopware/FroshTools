import template from './template.html.twig';
import './style.scss';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-feature-flags', {
    template,
    inject: ['froshToolsService'],
    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            featureFlags: null,
            isLoading: true,
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
            this.featureFlags = await this.froshToolsService.getFeatureFlags();
            this.isLoading = false;
        },
    },
});
