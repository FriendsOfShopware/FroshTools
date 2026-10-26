import template from './template.twig';
import '../frosh-tools-tab-index/style.scss';

const { Component, Mixin } = Shopware;

const SNIPPET_PREFIX = 'frosh-tools.tabs.data-integrity.recommendations';

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

    computed: {
        dataIntegrityRows() {
            return (this.dataIntegrityStatus ?? []).map((item) => ({
                ...item,
                title: this.titleFor(item),
            }));
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        recommendationFor(item) {
            return this.resultRecommendation(SNIPPET_PREFIX, item);
        },

        titleFor(item) {
            return this.resultTitle(SNIPPET_PREFIX, item);
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
            await this.createdComponent(true);
        },

        async createdComponent(forceRefresh = false) {
            this.isLoading = true;
            this.loadError = null;

            try {
                this.dataIntegrityStatus =
                    await this.froshToolsService.dataIntegrityStatus(
                        forceRefresh
                    );
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
