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
                // current latest: 2026-08-21
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
                id:   item.version,
                name: item.version,
            }));
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
                    this.version.available = Object.values(await this.froshToolsService.getDatabaseVersions());
                    this.version.target  ??= (this.version.available[this.version.available.length - 1] ?? null)?.version;
                }

                const version = this.version.target;
                this.diff     = await this.froshToolsService.getDatabaseDiff(version, this.introspection);

                this.version.selected  = version;
            } catch (error) {
                this.diff = null;
                this.loadError = error?.response?.data?.error ?? error.message;

                this.createNotificationError({ message: this.loadError });

                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async onVersionChanged() {
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
    },
});
