import template from './template.twig';
import './style.scss';

const { Component } = Shopware;

Component.register('frosh-tools-tab-index', {
    inject: ['FroshToolsService'],
    template,

    data() {
        return {
            healthStatus: null
        }
    },

    async created() {
        this.healthStatus = await this.FroshToolsService.healthStatus();
    },

    computed: {
        health() {
            if (this.isLoading) {
                return [];
            }

            return this.healthStatus;
        },

        isLoading() {
            return this.healthStatus === null
        },

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
