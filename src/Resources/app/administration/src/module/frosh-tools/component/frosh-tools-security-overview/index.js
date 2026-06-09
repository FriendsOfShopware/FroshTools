import template from './template.twig';
import './style.scss';

const { Component } = Shopware;

// Dependencies are summarised here and detailed on their own tab, so they are
// excluded from the per-finding list below.
const CATEGORY_ORDER = ['runtime', 'updates', 'configuration'];

const SEVERITY_RANK = {
    critical: 0,
    high: 1,
    medium: 2,
    low: 3,
    unknown: 4,
    ok: 5,
};

const SUMMARY_SEVERITIES = ['critical', 'high', 'medium', 'low'];

// Read-only findings list grouped by category. Receives findings from the parent
// Security Center so the data is fetched once.
Component.register('frosh-tools-security-overview', {
    template,
    props: {
        findings: {
            type: Array,
            default: () => [],
        },
        loading: {
            type: Boolean,
            default: false,
        },
        error: {
            type: String,
            default: null,
        },
    },
    computed: {
        groupedFindings() {
            const groups = {};
            for (const finding of this.findings || []) {
                const cat = finding.category || 'configuration';
                if (cat === 'dependencies') {
                    continue;
                }
                (groups[cat] = groups[cat] || []).push(finding);
            }

            const ordered = [];
            const seen = new Set();
            for (const cat of CATEGORY_ORDER) {
                if (groups[cat]) {
                    ordered.push({
                        category: cat,
                        findings: this.sort(groups[cat]),
                    });
                    seen.add(cat);
                }
            }
            for (const cat of Object.keys(groups)) {
                if (!seen.has(cat)) {
                    ordered.push({
                        category: cat,
                        findings: this.sort(groups[cat]),
                    });
                }
            }
            return ordered;
        },

        dependencyFindings() {
            return (this.findings || []).filter(
                (f) => f.category === 'dependencies'
            );
        },

        // Severity counts for the dependency advisories, e.g. [{severity, count}].
        // Only actionable severities are returned; an "ok"/"unknown"-only result is empty.
        dependencySummary() {
            const counts = {};
            for (const finding of this.dependencyFindings) {
                const sev = (finding.severity || '').toLowerCase();
                if (SUMMARY_SEVERITIES.includes(sev)) {
                    counts[sev] = (counts[sev] || 0) + 1;
                }
            }
            return SUMMARY_SEVERITIES.filter((sev) => counts[sev] > 0).map(
                (sev) => ({ severity: sev, count: counts[sev] })
            );
        },

        dependencyIssueCount() {
            return this.dependencySummary.reduce((sum, s) => sum + s.count, 0);
        },

        hasFindings() {
            return (this.findings || []).length > 0;
        },
    },
    methods: {
        sort(findings) {
            return [...findings].sort(
                (a, b) =>
                    (SEVERITY_RANK[a.severity] ?? 9) -
                    (SEVERITY_RANK[b.severity] ?? 9)
            );
        },

        categoryLabel(category) {
            return this.$t(`frosh-tools.tabs.security.categories.${category}`);
        },

        severityVariant(severity) {
            switch ((severity || '').toLowerCase()) {
                case 'critical':
                case 'high':
                    return 'danger';
                case 'medium':
                    return 'warning';
                case 'low':
                    return 'info';
                case 'ok':
                    return 'success';
                default:
                    return 'muted';
            }
        },

        severityLabel(severity) {
            return this.$t(
                `frosh-tools.tabs.security.severity.${(severity || 'unknown').toLowerCase()}`
            );
        },

        openUrl(url) {
            if (!url) return;
            window.open(url, '_blank', 'noopener');
        },

        goToDependencies() {
            this.$emit('navigate', 'dependencies');
        },
    },
});
