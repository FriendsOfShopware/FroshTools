import template from './template.twig';
import './style.scss';

const { Component } = Shopware;

Component.register('frosh-tools-tab-index', {
    inject: ['froshToolsService'],
    template,

    data() {
        return {
            isLoading: true,
            health: null
        }
    },

    created() {
        this.createdComponent();
    },

    computed: {
        columns() {
            return [
                {
                    property: 'status',
                    label: 'frosh-tools.status',
                    rawData: true
                },
                {
                    property: 'name',
                    label: 'frosh-tools.name',
                    rawData: true
                },
            ];
        },
    },

    methods: {
        async refresh() {
            this.isLoading = true;
            await this.createdComponent();
        },

        async createdComponent() {
            this.health = await this.froshToolsService.healthStatus();
            this.isLoading = false;
        },
    }
})
