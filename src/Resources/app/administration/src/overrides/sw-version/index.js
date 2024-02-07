import template from './template.twig';

const { Component } = Shopware;

Component.override('sw-version', {
    template,
    inject: ['froshToolsService', 'acl', 'loginService'],

    async created() {
        if(!this.checkPermission()) {
            return;
        }

        await this.checkHealth();
    },

    data() {
        return {
            health: null,
            hasPermission: false
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

            this.checkInterval = setInterval(async() => {
                try {
                    this.health = await this.froshToolsService.healthStatus();
                } catch (e) {
                    console.error(e);
                    clearInterval(this.checkInterval);
                }
            }, 60000);

            this.loginService.addOnLogoutListener(() => clearInterval(this.checkInterval));
        },

         checkPermission() {
            return this.hasPermission = this.acl.can('frosh_tools:read');
        }
    }
})
