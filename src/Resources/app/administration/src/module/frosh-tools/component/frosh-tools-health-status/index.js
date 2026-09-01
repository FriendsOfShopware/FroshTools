import './style.scss';
import template from './template.html.twig';
import { PRIVILEGE } from '../../acl/privileges';

const { Component } = Shopware;

Component.register('frosh-tools-health-status', {
    template,
    inject: ['froshToolsService', 'acl', 'loginService'],

    props: {
        collapsed: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            health: null,
            hasPermission: false,
            checkInterval: null,
        };
    },

    computed: {
        healthKey() {
            if (!this.health) {
                return 'ok';
            }

            let key = 'ok';

            for (const item of this.health) {
                if (item.state === 'STATE_ERROR') {
                    key = 'error';
                    continue;
                }

                if (item.state === 'STATE_WARNING' && key === 'ok') {
                    key = 'warning';
                }
            }

            return key;
        },

        isException() {
            return this.healthKey === 'warning' || this.healthKey === 'error';
        },

        meteorVariant() {
            return this.healthKey === 'error' ? 'critical' : 'attention';
        },

        badgeLabel() {
            return this.$t(`frosh-tools.healthStatus.badge.${this.healthKey}`);
        },

        healthPlaceholder() {
            return this.$t(`frosh-tools.healthStatus.${this.healthKey}`);
        },
    },

    created() {
        if (!this.checkPermission()) {
            return;
        }

        this.startPolling();
    },

    beforeUnmount() {
        this.stopPolling();
    },

    beforeDestroy() {
        this.stopPolling();
    },

    methods: {
        checkPermission() {
            return (this.hasPermission = this.acl.can(PRIVILEGE.READ));
        },

        async startPolling() {
            await this.refreshHealth();

            this.checkInterval = setInterval(async () => {
                try {
                    await this.refreshHealth();
                } catch (e) {
                    console.error(e);
                    this.stopPolling();
                }
            }, 60000);

            this.loginService.addOnLogoutListener(() => this.stopPolling());
        },

        async refreshHealth() {
            this.health = await this.froshToolsService.healthStatus(true);
        },

        stopPolling() {
            if (this.checkInterval) {
                clearInterval(this.checkInterval);
                this.checkInterval = null;
            }
        },
    },
});
