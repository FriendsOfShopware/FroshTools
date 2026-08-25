import template from './template.html.twig';
import './style.scss';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-database-diff', {
    template,
    inject: {
        froshToolsService: { from: 'froshToolsService' },
        froshToolsSearch: { default: null },
    },
    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('frosh-sortable-table'),
    ],

    data() {
        return {
            diff: null,
            isLoading: true,
            loadError: null,
            version: {
                selected: null,
                target: null,
                available: [],
            },
            introspection: false,
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        searchTerm() {
            return this.froshToolsSearch?.searchTerm ?? '';
        },

        visibleTables() {
            return this.filterRows(this.diff, this.searchTerm, [
                'table', 'name',
                'valueA', 'valueB',
            ]);
        },

        summary() {
            let summary = {};
            for (const item of this.diff) {
                const severity = this.severityForLabel(item.label);

                if (!summary.hasOwnProperty(severity)) {
                    summary[severity] = 0;
                }

                ++summary[severity];
            }
            return summary;
        },

        availableVersions() {
            return this.version.available.map((item) => ({
                id:   item.slug,
                name: item.version,
            }));
        },

        selectedVersion() {
            return this.version.target?.slug;
        },
    },

    methods: {
        async refresh() {
            await this.load(true);
        },

        async createdComponent() {
            await this.load(true);
        },

        async load(forceRefresh = false) {
            this.isLoading = true;
            this.loadError = null;

            try {
                if (forceRefresh || !this.version.available.length) {
                    await this.loadVersions(forceRefresh);
                }

                const version = this.version.target?.version;
                if (!version) {
                    throw new Error('No target version selected');
                }

                this.diff = await this.froshToolsService.getDatabaseDiff(version, this.introspection, forceRefresh);

                this.version.selected = this.version.target;
            } catch (error) {
                this.diff = null;
                this.loadError = error?.response?.data?.error ?? error.message;

                this.createNotificationError({ message: this.loadError });

                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async loadVersions(forceRefresh = false) {
            this.version.available = Object.values(await this.froshToolsService.getDatabaseVersions(forceRefresh));

            if (this.version.target) {
                return;
            }

            this.version.target = this.version.available[this.version.available.length - 1] ?? null;
        },

        async onVersionChanged(slug) {
            this.version.target = this.version.available.find(item => item.slug === slug) ?? null;

            await this.load();
        },

        async onChangeIntrospection(introspection) {
            this.introspection = introspection;

            await this.load();
        },

        variantForLabel(label) {
            switch (label) {
                case 'added':
                    return 'success';
                case 'removed':
                    return 'danger';
                case 'modified':
                    return 'warning';
                default:
                    throw new Error(`Unknown label type ${label}`);
            }
        },

        severityForLabel(label) {
            switch (label) {
                case 'added':
                    return 'low';
                case 'removed':
                    return 'high';
                case 'modified':
                    return 'medium';
                default:
                    throw new Error(`Unknown label type ${label}`);
            }
        },

        openLinkForVersion(version) {
            if (!version.slug) return;

            const url = `https://swdb.dev/version/${version.slug}/`;

            window.open(url, '_blank');
        },
    },
});
