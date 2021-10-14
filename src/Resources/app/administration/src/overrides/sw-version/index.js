import template from './template.twig';

const { Component } = Shopware;

Component.override('sw-version', {
    template,
    inject: ['froshToolsService'],

    async created() {
        await this.checkHealth();
    },

    data() {
        return {
            health: null
        }
    },

    computed: {
        healthVariant() {
            let variant = 'success';

            for (let health of this.health) {
                if (health.state === 'STATE_ERROR') {
                    variant = 'error';
                    continue;
                }

                if (health.state === 'STATE_WARNING' && variant === 'success') {
                    variant = 'warning';
                }
            }

            return variant;
        },

        healthPlaceholder() {
            let msg = 'Shop Status: Ok';

            if (this.health === null) {
                return msg;
            }

            for (let health of this.health) {
                if (health.state === 'STATE_ERROR') {
                    msg = 'Shop Status: May outage, Check System Status';
                    continue;
                }

                if (health.state === 'STATE_WARNING' && msg === 'Shop Status: Ok') {
                    msg = 'Shop Status: Issues, Check System Status';
                }
            }

            return msg;
        }
    },

    methods: {
        async checkHealth() {
            this.health = await this.froshToolsService.healthStatus();

            setInterval(async() => {
                this.health = await this.froshToolsService.healthStatus();
            }, 10000);
        }
    }
})
