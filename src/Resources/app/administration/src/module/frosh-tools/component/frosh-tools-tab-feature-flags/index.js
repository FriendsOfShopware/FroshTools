import template from './template.html.twig';
import './style.scss';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-feature-flags', {
    template,

    inject: ['froshToolsService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            featureFlags: null,
            isLoading: true,
        }
    },

    created() {
        this.createdComponent();
    },

    computed: {
        columns() {
            return [
                {
                    property: 'flag',
                    label: 'frosh-tools.tabs.feature-flags.flag',
                    rawData: true,
                },
                {
                    property: 'active',
                    label: 'frosh-tools.active',
                    rawData: true,
                },
                {
                    property: 'description',
                    label: 'frosh-tools.tabs.feature-flags.description',
                    rawData: true,
                },
                {
                    property: 'major',
                    label: 'frosh-tools.tabs.feature-flags.major',
                    rawData: true,
                },
                {
                    property: 'default',
                    label: 'frosh-tools.tabs.feature-flags.default',
                    rawData: true,
                },
            ];
        },
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

        async toggle(flag) {
            this.isLoading = true;
            await this.froshToolsService.toggleFeatureFlag(flag)
                .then(async () => {
                    this.featureFlags = await this.froshToolsService.getFeatureFlags();
                    window.location.reload();
                })
                .catch((error) => {
                    try {
                        this.createNotificationError({
                            message: error.response.data.errors[0].detail
                        });
                    }
                    catch (e) {
                        console.error(error);
                    }
                    this.isLoading = false;
                });
        }
    }
});
