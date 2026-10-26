import template from './template.twig';
import recommendationCode from './recommendation-code.js';
import './style.scss';

const { Component, Mixin } = Shopware;

const SNIPPET_PREFIX = 'frosh-tools.tabs.index.recommendations';

Component.register('frosh-tools-tab-index', {
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
            health: null,
            performanceStatus: null,
            activeInfo: null,
        };
    },

    computed: {
        healthRows() {
            return this.withTitles(this.health);
        },

        performanceRows() {
            return this.withTitles(this.performanceStatus);
        },

        checkSummary() {
            if (this.isLoading || this.loadError) return null;
            const checks = [
                ...(this.health || []),
                ...(this.performanceStatus || []),
            ];
            if (!checks.length) return null;

            const errors = checks.filter(
                (check) => check.state === 'STATE_ERROR'
            ).length;
            const warnings = checks.filter(
                (check) => check.state === 'STATE_WARNING'
            ).length;
            const variant = errors ? 'danger' : warnings ? 'warning' : 'info';

            return {
                variant,
                icon: errors || warnings ? 'alert' : 'activity',
                title: this.$t(
                    `frosh-tools.tabs.index.summary.${errors || warnings ? 'attention' : 'reviewed'}`
                ),
                body: this.$t('frosh-tools.tabs.index.summary.detail', {
                    total: checks.length,
                    errors,
                    warnings,
                }),
            };
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        recommendationFor(item) {
            const recommendation = this.resultRecommendation(
                SNIPPET_PREFIX,
                item
            );
            if (!recommendation) {
                return null;
            }

            return {
                ...recommendation,
                code: recommendationCode[item.id] ?? null,
            };
        },

        titleFor(item) {
            return this.resultTitle(SNIPPET_PREFIX, item);
        },

        withTitles(items) {
            return (items ?? []).map((item) => ({
                ...item,
                title: this.titleFor(item),
            }));
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
                [this.health, this.performanceStatus] = await Promise.all([
                    this.froshToolsService.healthStatus(),
                    this.froshToolsService.performanceStatus(),
                ]);
            } catch (error) {
                this.health = null;
                this.performanceStatus = null;
                this.loadError = error?.response?.data?.error ?? error.message;
                this.createNotificationError({ message: this.loadError });
            } finally {
                this.isLoading = false;
            }
        },
    },
});
