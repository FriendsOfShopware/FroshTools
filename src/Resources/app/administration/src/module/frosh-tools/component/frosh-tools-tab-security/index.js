import template from './template.twig';
import './style.scss';

const { Component, Mixin } = Shopware;

const VALID_TABS = ['overview', 'dependencies', 'files'];
const NOTICE_KEY = 'frosh-tools.security.notice-dismissed';

// Security Center container: owns the posture summary (fetched once) and the
// sub-tab navigation. Read-only findings live on Overview; the interactive
// Composer and File Integrity workflows each get their own sub-tab.
Component.register('frosh-tools-tab-security', {
    template,
    inject: ['froshToolsService'],
    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: true,
            error: null,
            activeTab: 'overview',
            noticeDismissed: false,
            summary: {
                critical: 0,
                high: 0,
                medium: 0,
                low: 0,
                unknown: 0,
                ok: 0,
            },
            findings: [],
        };
    },

    created() {
        const requested = this.$route?.query?.section;
        if (VALID_TABS.includes(requested)) {
            this.activeTab = requested;
        }
        try {
            this.noticeDismissed =
                window.localStorage.getItem(NOTICE_KEY) === '1';
        } catch {
            /* localStorage unavailable */
        }
        this.load();
    },

    computed: {
        tabs() {
            return [
                {
                    key: 'overview',
                    label: this.$t(
                        'frosh-tools.tabs.security.sections.overview'
                    ),
                    badge: this.findingBadge,
                    badgeVariant: this.findingBadgeVariant,
                },
                {
                    key: 'dependencies',
                    label: this.$t(
                        'frosh-tools.tabs.security.sections.dependencies'
                    ),
                    badge: this.dependencyBadge,
                    badgeVariant: 'danger',
                },
                {
                    key: 'files',
                    label: this.$t('frosh-tools.tabs.security.sections.files'),
                    badge: null,
                    badgeVariant: 'muted',
                },
            ];
        },

        actionable() {
            return (
                this.summary.critical +
                this.summary.high +
                this.summary.medium +
                this.summary.low
            );
        },

        findingBadge() {
            return this.actionable > 0 ? this.actionable : null;
        },

        findingBadgeVariant() {
            if (this.summary.critical > 0 || this.summary.high > 0)
                return 'danger';
            if (this.summary.medium > 0 || this.summary.low > 0)
                return 'warning';
            return 'muted';
        },

        dependencyBadge() {
            const deps = (this.findings || []).filter(
                (f) =>
                    f.category === 'dependencies' &&
                    f.severity !== 'ok' &&
                    f.severity !== 'unknown'
            );
            return deps.length > 0 ? deps.length : null;
        },
    },

    methods: {
        async load() {
            this.isLoading = true;
            this.error = null;
            try {
                const data = await this.froshToolsService.getSecurityStatus();
                this.summary = {
                    critical: data?.summary?.critical || 0,
                    high: data?.summary?.high || 0,
                    medium: data?.summary?.medium || 0,
                    low: data?.summary?.low || 0,
                    unknown: data?.summary?.unknown || 0,
                    ok: data?.summary?.ok || 0,
                };
                this.findings = Array.isArray(data?.findings)
                    ? data.findings
                    : [];
            } catch (e) {
                this.error = e?.message || 'Unknown error';
                this.findings = [];
            } finally {
                this.isLoading = false;
            }
        },

        async refresh() {
            await this.load();
        },

        dismissNotice() {
            this.noticeDismissed = true;
            try {
                window.localStorage.setItem(NOTICE_KEY, '1');
            } catch {
                /* localStorage unavailable */
            }
        },

        selectTab(key) {
            if (this.activeTab === key) return;
            this.activeTab = key;
            // keep the section deep-linkable without polluting browser history
            const query = { ...(this.$route?.query || {}), section: key };
            this.$router?.replace({ query }).catch(() => {});
        },
    },
});
