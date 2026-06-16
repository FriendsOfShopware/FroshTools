import template from './template.twig';
import './style.scss';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-shopmon', {
    template,
    inject: ['froshToolsService'],
    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: true,
            isSettingUp: false,
            status: null,
            showRemoveModal: false,
            copiedField: null,
        };
    },

    computed: {
        isConfigured() {
            return this.status?.configured === true;
        },
    },

    created() {
        this.loadStatus();
    },

    methods: {
        async loadStatus() {
            this.isLoading = true;
            try {
                this.status = await this.froshToolsService.getShopmonStatus();
            } finally {
                this.isLoading = false;
            }
        },

        async setup() {
            this.isSettingUp = true;
            try {
                this.status = await this.froshToolsService.setupShopmon();
                this.createNotificationSuccess({
                    message: this.$t('frosh-tools.tabs.shopmon.setup.success'),
                });
            } catch {
                this.createNotificationError({
                    message: this.$t('frosh-tools.tabs.shopmon.setup.error'),
                });
            } finally {
                this.isSettingUp = false;
            }
        },

        async removeIntegration() {
            this.showRemoveModal = false;
            this.isLoading = true;
            try {
                await this.froshToolsService.removeShopmon();
                this.createNotificationSuccess({
                    message: this.$t('frosh-tools.tabs.shopmon.remove.success'),
                });
            } finally {
                await this.loadStatus();
            }
        },

        async copy(field, value) {
            try {
                await navigator.clipboard.writeText(value);
                this.copiedField = field;
                window.setTimeout(() => {
                    if (this.copiedField === field) {
                        this.copiedField = null;
                    }
                }, 2000);
            } catch {
                this.createNotificationError({
                    message: this.$t('frosh-tools.tabs.shopmon.copyError'),
                });
            }
        },
    },
});
