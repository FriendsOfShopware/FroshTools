import template from './template.twig';

const { Component } = Shopware;


Component.override('sw-version', {
    template,
    inject: ['FroshToolsService'],

    async created() {
        this.health = await this.FroshToolsService.healthStatus();
    },

    data() {
        return {
            health: null
        }
    },

    computed: {
        healthVariant() {
            let color = 'green';

            for (let health of this.health) {
                if (health.state === 'STATE_ERROR') {
                    color = 'red';
                    continue;
                }

                if (health.state === 'STATE_WARNING' && color === 'success') {
                    color = 'orange';
                }
            }

            return color;
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
        openSystemStatus() {
            this.$router.push({
                name: 'frosh.tools.index.index'
            })
        }
    }
})
