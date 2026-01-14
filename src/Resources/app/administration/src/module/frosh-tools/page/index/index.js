import './frosh-tools.scss';
import template from './template.twig';

const { Component } = Shopware;

Component.register('frosh-tools-index', {
    template,
    inject: ['froshToolsService'],

    data() {
        return {
            fastlyEnabled: false,
        };
    },

    created() {
        this.checkFastly();
    },

    methods: {
        async checkFastly() {
            try {
                const response = await this.froshToolsService.getFastlyStatus();
                this.fastlyEnabled = response.enabled;
            } catch {
                this.fastlyEnabled = false;
            }
        },
    },

    computed: {
        elasticsearchAvailable() {
            try {
                return (
                    Shopware.Store.get('context').app.config.settings
                        ?.elasticsearchEnabled || false
                );
            } catch {
                return (
                    Shopware.State.get('context').app.config.settings
                        ?.elasticsearchEnabled || false
                );
            }
        },
    },
});
