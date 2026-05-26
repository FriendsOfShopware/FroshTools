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
                if (!groups[advisory.packageName]) {
                    groups[advisory.packageName] = {
                        packageName: advisory.packageName,
                        installedVersion: advisory.installedVersion,
                        advisories: [],
                    };
                }
                groups[advisory.packageName].advisories.push(advisory);
            }
            return Object.values(groups);
        },

        affectedPackages() {
            return this.groupedAdvisories.map((g) => g.packageName);
        },

        updateCommand() {
            const pkgs = this.affectedPackages;
            if (pkgs.length === 0 || pkgs.length > 5) {
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
