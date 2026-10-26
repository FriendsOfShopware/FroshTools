import template from './template.twig';
import entities from './entities.js';
import './style.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('frosh-tools-data-integrity-affected', {
    inject: ['froshToolsService', 'repositoryFactory'],
    mixins: [Mixin.getByName('frosh-settings-result')],
    template,

    props: {
        checkId: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            isLoading: true,
            loadError: null,
            unavailable: false,
            forbidden: false,
            total: 0,
            page: 1,
            limit: 10,
            rows: [],
        };
    },

    computed: {
        relatedLabel() {
            const key = `frosh-tools.tabs.data-integrity.recommendations.${this.checkId}.related`;

            return this.snippetExists(key)
                ? this.$t(key)
                : this.$t('frosh-tools.tabs.data-integrity.affected.related');
        },
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            this.isLoading = true;
            this.loadError = null;

            try {
                const result =
                    await this.froshToolsService.dataIntegrityAffected(
                        this.checkId,
                        this.page,
                        this.limit
                    );
                this.total = result.total;
                this.rows = await this.resolveRows(
                    result.entity,
                    result.ids,
                    result.related ?? {}
                );
            } catch (error) {
                this.rows = [];
                if (error?.response?.status === 404) {
                    this.unavailable = true;
                } else if (error?.response?.status === 403) {
                    this.forbidden = true;
                } else {
                    this.loadError =
                        error?.response?.data?.error ?? error.message;
                }
            } finally {
                this.isLoading = false;
            }
        },

        async resolveRows(entityName, ids, related) {
            const config = entities[entityName] ?? null;
            const relatedIds = ids.flatMap((id) => related[id] ?? []);
            const records = await this.loadRecords(entityName, config, [
                ...new Set([...ids, ...relatedIds]),
            ]);

            return ids.map((id) => ({
                ...this.toRow(id, config, records),
                related: (related[id] ?? []).map((relatedId) =>
                    this.toRow(relatedId, config, records)
                ),
            }));
        },

        toRow(id, config, records) {
            const record = records.get(id) ?? null;

            return {
                id,
                label: record ? config.label(record) : null,
                detail: record ? config.detail(record) : null,
                href: config
                    ? this.$router.resolve(config.route(id, record)).href
                    : null,
            };
        },

        async loadRecords(entityName, config, ids) {
            if (!config || ids.length === 0) {
                return new Map();
            }

            const criteria = new Criteria(1, ids.length);
            criteria.setIds(ids);
            (config.associations ?? []).forEach((association) => {
                criteria.addAssociation(association);
            });

            try {
                const result = await this.repositoryFactory
                    .create(entityName)
                    .search(criteria, {
                        ...Shopware.Context.api,
                        inheritance: true,
                    });

                return new Map(result.map((record) => [record.id, record]));
            } catch {
                return new Map();
            }
        },

        async onPageChange({ page, limit }) {
            this.page = page;
            this.limit = limit;
            await this.load();
        },
    },
});
