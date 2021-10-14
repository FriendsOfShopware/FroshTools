import template from './template.twig';
import './style.scss';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-files', {
    template,
    inject: ['repositoryFactory', 'froshToolsService'],
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
        },

        isLoadingClass() {
            return {
                'is-loading': this.isLoading
            }
        },
    },

    methods: {
        async createdComponent() {
            this.items = (await this.froshToolsService.getShopwareFiles()).data;
            this.isLoading = false;
        }
    }
});
