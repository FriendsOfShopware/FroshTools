import template from './template.twig';
import './style.scss';

const { Component } = Shopware;

Component.register('frosh-tools-tab-index', {
    inject: ['froshToolsService'],
    template,

    data() {
        return {
            isLoading: true,
            health: null,
            performanceStatus: null,
        }
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
                    rawData: true
                },
                {
                    property: 'current',
                    label: 'frosh-tools.current',
                    rawData: true
                },
                {
                    property: 'recommended',
                    label: 'frosh-tools.recommended',
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
            this.performanceStatus = await this.froshToolsService.performanceStatus();
            this.isLoading = false;
        },
    }
})
