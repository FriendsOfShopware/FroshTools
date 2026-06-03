import template from './template.twig';
import './style.scss';

const { Component } = Shopware;

// Composer dependency advisories, grouped by package. Owns its own fetch because
// the package-grouped detail is richer than the flattened findings on Overview.
Component.register('frosh-tools-security-dependencies', {
    template,
    inject: ['froshToolsService'],

    data() {
        return {
            isLoading: true,
            copiedCommand: null,
            result: {
                packages: 0,
                vulnerable: 0,
                advisories: [],
                error: null,
                cachedAt: null,
            },
        };
    },

    created() {
        this.load();
    },

    computed: {
        hasError() {
            return Boolean(this.result.error);
        },
        hasAdvisories() {
            return (
                Array.isArray(this.result.advisories) &&
                this.result.advisories.length > 0
            );
        },
        groupedAdvisories() {
            const groups = {};
            for (const advisory of this.result.advisories || []) {
                // The same package can be installed at different versions across vendor
                // dirs (project root vs. a plugin's bundled copy), so group by both.
                const key = `${advisory.packageName}@${advisory.installedVersion || ''}`;
                if (!groups[key]) {
                    groups[key] = {
                        packageName: advisory.packageName,
                        installedVersion: advisory.installedVersion,
                        sources: advisory.installedSources || [],
                        advisories: [],
                    };
                }
                groups[key].advisories.push(advisory);
            }
            return Object.values(groups);
        },

        affectedPackages() {
            // Unique, sorted list of the packages that actually have advisories.
            return [
                ...new Set(this.groupedAdvisories.map((g) => g.packageName)),
            ].sort();
        },

        // Update only the affected packages explicitly. `--with-dependencies` is
        // still required so Composer may also bump their dependencies when a
        // patched version needs it — but unrelated packages stay untouched.
        updateCommand() {
            const pkgs = this.affectedPackages;
            if (pkgs.length === 0) {
                return 'composer update --with-dependencies';
            }
            return `composer update ${pkgs.join(' ')} --with-dependencies`;
        },
    },

    methods: {
        async load(forceRefresh = false) {
            this.isLoading = true;
            try {
                const data =
                    await this.froshToolsService.getComposerAudit(forceRefresh);
                this.result = {
                    packages: data.packages || 0,
                    vulnerable: data.vulnerable || 0,
                    advisories: data.advisories || [],
                    error: data.error || null,
                    cachedAt: data.cachedAt || null,
                };
            } catch (e) {
                this.result = {
                    packages: 0,
                    vulnerable: 0,
                    advisories: [],
                    error: e?.message || 'Unknown error',
                    cachedAt: null,
                };
            } finally {
                this.isLoading = false;
            }
        },

        async refresh() {
            await this.load(true);
        },

        cachedAtLabel() {
            if (!this.result.cachedAt) {
                return null;
            }
            try {
                return new Date(this.result.cachedAt * 1000).toLocaleString();
            } catch {
                return null;
            }
        },

        severityVariant(severity) {
            switch ((severity || '').toLowerCase()) {
                case 'critical':
                case 'high':
                    return 'danger';
                case 'medium':
                case 'moderate':
                    return 'warning';
                case 'low':
                    return 'info';
                default:
                    return 'muted';
            }
        },

        severityLabel(severity) {
            if (!severity) {
                return this.$t(
                    'frosh-tools.tabs.composerAudit.severityUnknown'
                );
            }
            return severity.charAt(0).toUpperCase() + severity.slice(1);
        },

        sourceLabel(source) {
            if (source === 'project') {
                return this.$t('frosh-tools.tabs.composerAudit.sourceProject');
            }
            return source;
        },

        openUrl(url) {
            if (!url) return;
            window.open(url, '_blank', 'noopener');
        },

        async copyCommand(command) {
            try {
                await navigator.clipboard.writeText(command);
                this.copiedCommand = command;
                setTimeout(() => {
                    if (this.copiedCommand === command) {
                        this.copiedCommand = null;
                    }
                }, 2000);
            } catch {
                this.createNotificationError({
                    message: command,
                });
            }
        },
    },
});
