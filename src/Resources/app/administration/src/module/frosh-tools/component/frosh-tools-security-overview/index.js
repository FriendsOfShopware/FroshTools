import template from './template.twig';
import './style.scss';

const { Component } = Shopware;

const CATEGORY_ORDER = ['dependencies', 'runtime', 'updates', 'configuration'];

const SEVERITY_RANK = {
    critical: 0,
    high: 1,
    medium: 2,
    low: 3,
    unknown: 4,
    ok: 5,
};

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
    },
});
