import template from './template.twig';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-files', {
    template,
    inject: ['repositoryFactory', 'FroshToolsService'],
    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            items: {},
            isLoading: true
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        columns() {
            return [
                {
                    property: 'name',
                    label: 'frosh-tools.name',
                    rawData: true,
                    primary: true
                }
            ];
        }
    },

    methods: {
        async createdComponent() {
            this.items = (await this.FroshToolsService.getShopwareFiles()).data;
            this.isLoading = false;
        }
    }
});
