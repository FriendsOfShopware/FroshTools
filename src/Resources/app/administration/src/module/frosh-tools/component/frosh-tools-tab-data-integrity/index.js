import template from './template.twig';
import '../frosh-tools-tab-index/style.scss';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-data-integrity', {
    inject: ['froshToolsService'],
    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('frosh-sortable-table'),
        Mixin.getByName('frosh-settings-result'),
    ],
    template,

    data() {
        return {
            isLoading: true,
            loadError: null,
            dataIntegrityStatus: null,
            activeInfo: null,
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        recommendationFor(item) {
            const key = `frosh-tools.tabs.data-integrity.recommendations.${item?.id}`;
            if (!item || !this.$te(`${key}.description`, 'en-GB')) {
                return null;
            }

            return {
                description: this.$t(`${key}.description`),
                solution: this.$te(`${key}.solution`, 'en-GB')
                    ? this.$t(`${key}.solution`)
                    : null,
            };
        },

        hasInfo(item) {
            return Boolean(this.recommendationFor(item));
        },

        openInfo(item) {
            this.activeInfo = item;
        },

        closeInfo() {
            this.activeInfo = null;
        },

        async refresh() {
            await this.createdComponent();
        },

        async createdComponent() {
            this.isLoading = true;
            this.loadError = null;

            try {
                this.dataIntegrityStatus =
                    await this.froshToolsService.dataIntegrityStatus();
            } catch (error) {
                this.dataIntegrityStatus = null;
                this.loadError = error?.response?.data?.error ?? error.message;
                this.createNotificationError({ message: this.loadError });
            } finally {
                this.isLoading = false;
            }
        },
    },
});
