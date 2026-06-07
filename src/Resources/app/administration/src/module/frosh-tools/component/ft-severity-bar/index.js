import './style.scss';
import template from './template.html.twig';

const { Component } = Shopware;

const SEGMENTS = [
    { key: 'critical', variant: 'danger' },
    { key: 'high', variant: 'danger' },
    { key: 'medium', variant: 'warning' },
    { key: 'low', variant: 'info' },
    { key: 'unknown', variant: 'muted' },
    { key: 'ok', variant: 'success' },
];

// Segmented posture bar: each severity occupies a share of the bar proportional
// to its count. Counts are always rendered as text so meaning is never carried
// by colour alone (WCAG color-not-only).
Component.register('ft-severity-bar', {
    props: {
        summary: {
            type: Object,
            required: true,
        },
        loading: {
            type: Boolean,
            default: false,
        },
    },
    computed: {
        total() {
            return SEGMENTS.reduce(
                (sum, s) => sum + (this.summary[s.key] || 0),
                0
            );
        },

        segments() {
            const total = this.total || 1;
            return SEGMENTS.filter((s) => (this.summary[s.key] || 0) > 0).map(
                (s) => ({
                    key: s.key,
                    variant: s.variant,
                    count: this.summary[s.key] || 0,
                    pct: ((this.summary[s.key] || 0) / total) * 100,
                })
            );
        },

        actionable() {
            return (
                (this.summary.critical || 0) +
                (this.summary.high || 0) +
                (this.summary.medium || 0) +
                (this.summary.low || 0)
            );
        },

        verdictVariant() {
            if ((this.summary.critical || 0) > 0) return 'danger';
            if ((this.summary.high || 0) > 0) return 'danger';
            if ((this.summary.medium || 0) > 0 || (this.summary.low || 0) > 0)
                return 'warning';
            if ((this.summary.unknown || 0) > 0) return 'info';
            return 'success';
        },

        verdictLabel() {
            if ((this.summary.critical || 0) > 0) {
                return this.$t('frosh-tools.tabs.security.verdict.critical', {
                    count: this.summary.critical,
                });
            }
            if (this.actionable > 0) {
                return this.$t('frosh-tools.tabs.security.verdict.atRisk', {
                    count: this.actionable,
                });
            }
            if ((this.summary.unknown || 0) > 0) {
                return this.$t('frosh-tools.tabs.security.verdict.unknown');
            }
            return this.$t('frosh-tools.tabs.security.verdict.healthy');
        },

        countParts() {
            return SEGMENTS.filter((s) => (this.summary[s.key] || 0) > 0).map(
                (s) => ({
                    key: s.key,
                    variant: s.variant,
                    count: this.summary[s.key] || 0,
                    label: this.$t(
                        `frosh-tools.tabs.security.severity.${s.key}`
                    ),
                })
            );
        },
    },
    methods: {
        segmentLabel(seg) {
            return `${seg.count} ${this.$t(`frosh-tools.tabs.security.severity.${seg.key}`)}`;
        },
    },
    template,
});
