import template from './template.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('frosh-tools-webhook-list', {
    template,

    inject: ['repositoryFactory', 'acl', 'filterFactory'],

    mixins: [Mixin.getByName('listing'), Mixin.getByName('notification')],

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    data() {
        return {
            webhooks: null,
            sortBy: 'name',
            sortDirection: 'ASC',
            isLoading: false,
            showDeleteModal: false,
            filterCriteria: [],
            defaultFilters: ['active-filter', 'app-filter'],
            storeKey: 'grid.filter.frosh-tools-webhook',
            activeFilterNumber: 0,
        };
    },

    computed: {
        webhookRepository() {
            return this.repositoryFactory.create('webhook');
        },

        listFilterOptions() {
            return {
                'active-filter': {
                    property: 'active',
                    label: this.$tc(
                        'frosh-tools-webhook.list.filter.active.label'
                    ),
                    placeholder: this.$tc(
                        'frosh-tools-webhook.list.filter.active.placeholder'
                    ),
                },
                'app-filter': {
                    property: 'app',
                    label: this.$tc(
                        'frosh-tools-webhook.list.filter.app.label'
                    ),
                    placeholder: this.$tc(
                        'frosh-tools-webhook.list.filter.app.placeholder'
                    ),
                },
            };
        },

        listFilters() {
            return this.filterFactory.create('webhook', this.listFilterOptions);
        },
    },

    methods: {
        getList() {
            const criteria = new Criteria(this.page, this.limit);
            this.isLoading = true;

            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
            criteria.addAssociation('app');

            this.filterCriteria.forEach((filter) => {
                criteria.addFilter(filter);
            });

            this.activeFilterNumber = this.filterCriteria.length;

            return this.webhookRepository
                .search(criteria)
                .then((items) => {
                    this.total = items.total;
                    this.webhooks = items;
                    this.isLoading = false;
                })
                .catch(() => {
                    this.isLoading = false;
                });
        },

        onInlineEditSave(promise) {
            promise
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc(
                            'frosh-tools-webhook.detail.messageSaveSuccess'
                        ),
                    });
                })
                .catch(() => {
                    this.getList();
                    this.createNotificationError({
                        message: this.$tc(
                            'frosh-tools-webhook.detail.messageSaveError'
                        ),
                    });
                });
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.webhookRepository.delete(id).then(() => {
                this.getList();
            });
        },

        getWebhookColumns() {
            return [
                {
                    property: 'name',
                    label: 'frosh-tools-webhook.list.columnName',
                    routerLink: 'frosh.tools.webhook.detail',
                    inlineEdit: 'string',
                    primary: true,
                },
                {
                    property: 'eventName',
                    label: 'frosh-tools-webhook.list.columnEventName',
                    inlineEdit: 'string',
                },
                {
                    property: 'url',
                    label: 'frosh-tools-webhook.list.columnUrl',
                    inlineEdit: 'string',
                },
                {
                    property: 'active',
                    label: 'frosh-tools-webhook.list.columnActive',
                    inlineEdit: 'boolean',
                    align: 'center',
                },
                {
                    property: 'errorCount',
                    label: 'frosh-tools-webhook.list.columnErrorCount',
                    align: 'right',
                },
                {
                    property: 'app.name',
                    label: 'frosh-tools-webhook.list.columnApp',
                },
            ];
        },
    },
});
