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
        this.isLoading = false;
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
    }
})
