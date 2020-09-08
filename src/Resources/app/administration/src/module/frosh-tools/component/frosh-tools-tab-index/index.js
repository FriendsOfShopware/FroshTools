import template from './template.twig';

const { Component } = Shopware;

Component.register('frosh-tools-tab-index', {
    inject: ['FroshToolsService'],
    template,

    data() {
        return {
            isLoading: true,
            health: null
        }
    },

    async created() {
        this.health = await this.FroshToolsService.healthStatus();
    },

    computed: {
        columns() {
            return [
                {
                    property: 'status',
                    label: 'Status',
                    rawData: true
                },
                {
                    property: 'name',
                    label: 'Name',
                    rawData: true
                },
            ];
        },
    }
})
