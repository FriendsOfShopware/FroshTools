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

    computed: {
        activeCount()  { return this.countWhere('active'); },
        majorCount()   { return this.countWhere('major'); },
        defaultCount() { return this.countWhere('default'); },
    },

    methods: {
        countWhere(field) {
            if (!this.featureFlags) return 0;
            return this.featureFlags.filter((f) => !!f[field]).length;
        },

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
