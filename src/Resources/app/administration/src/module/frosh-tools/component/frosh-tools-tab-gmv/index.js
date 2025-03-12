import template from './template.twig';
import './style.scss';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-gmv', {
    template,
    inject: ['repositoryFactory', 'froshToolsService'],
    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            gmvEntries: [],
            isLoading: true,
            numberFormater: null
        };
    },

    created() {
        const language = Shopware.Application.getContainer('factory').locale.getLastKnownLocale();
        this.numberFormater = new Intl.NumberFormat(
            language,
            { minimumFractionDigits: 2, maximumFractionDigits: 2 }
        );

        this.createdComponent();
    },

    computed: {
        columns() {
            return [

                {
                    property: 'date',
                    label: 'frosh-tools.tabs.gmv.date',
                    rawData: true
                },
                {
                    property: 'order_count',
                    label: 'frosh-tools.tabs.gmv.orderCount',
                    rawData: true,
                    align: 'right'
                },
                {
                    property: 'turnover_total',
                    label: 'frosh-tools.tabs.gmv.turnoverGross',
                    rawData: true,
                    align: 'right'
                },
                {
                    property: 'turnover_net',
                    label: 'frosh-tools.tabs.gmv.turnoverNet',
                    rawData: true,
                    align: 'right'
                }
            ];
        }
    },

    methods: {
        async refresh() {
            this.isLoading = true;
            await this.createdComponent();
        },

        async createdComponent() {
            this.gmvEntries = await this.froshToolsService.getGmv();

            this.isLoading = false;
        },

        formatCurrency(amount, currency) {
            return this.numberFormater.format(amount) + ' ' + currency;
        },
    }
});
