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
            if (this.health.state === 'STATE_OK') {
                return 'success';
            }

            if (this.health.state === 'STATE_WARNING') {
                return 'warning';
            }

            return 'error';
        },

        healthPlaceholder() {
            if (this.health === null) {
                return 'Shop Status: unknown';
            }

            if (this.health.state === 'STATE_OK') {
                return 'Shop Status: Ok';
            }

            if (this.health.state === 'STATE_WARNING') {
                return 'Shop Status: Issues, Check System Status';
            }

            return 'Shop Status: May outage, Check System Status';
        }
    },

    methods: {
        async checkHealth() {
            this.health = await this.froshToolsService.healthCheck();

            this.checkInterval = setInterval(async() => {
                try {
                    this.health = await this.froshToolsService.healthCheck(true);
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
