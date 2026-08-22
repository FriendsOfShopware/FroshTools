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
                target: '6.7.12.2',
                // TODO: Populate
                current: null,
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
    },

    methods: {
        async refresh() {
            await this.load();
        },

        async createdComponent() {
            await this.load();
        },

        async load() {
            this.isLoading = true;
            this.loadError = null;

            // TODO: Only refresh if matching version number pattern "6|5.X.Y.Z".

            try {
                const version = this.version.target;
                // TODO: Add support for "introspection" query parameter and current database inspection.
                this.diff =
                    await this.froshToolsService.getDatabaseDiff(version, this.introspection);

                this.version.selected = version;
            } catch (error) {
                this.diff = null;
                this.loadError = error?.response?.data?.error ?? error.message;

                this.createNotificationError({ message: this.loadError });

                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        onVersionChange(version) {
            // TODO: Validate pattern?
            // TODO: Or switch to sw-select component (with available-versions endpoint).
        },

        onChangeIntrospection(introspection) {
            this.introspection = introspection;

            this.refresh();
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
