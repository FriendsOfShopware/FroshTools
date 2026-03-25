import template from './template.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Component.getComponentHelper();

Component.register('frosh-tools-webhook-detail', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('notification')],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.allowSave;
            },
            method: 'onSave',
        },
        ESCAPE: 'onCancel',
    },

    props: {
        webhookId: {
            type: String,
            required: false,
            default: null,
        },
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    data() {
        return {
            webhook: null,
            isLoading: false,
            isSaveSuccessful: false,
            eventLogs: null,
            eventLogsLoading: false,
            eventLogsPage: 1,
            eventLogsLimit: 10,
            eventLogsTotal: 0,
        };
    },

    computed: {
        identifier() {
            return this.webhook?.name ?? '';
        },

        webhookRepository() {
            return this.repositoryFactory.create('webhook');
        },

        eventLogRepository() {
            return this.repositoryFactory.create('webhook_event_log');
        },

        isNewWebhook() {
            return this.webhook?.isNew?.() ?? false;
        },

        isManagedByApp() {
            return !!this.webhook?.appId;
        },

        allowSave() {
            if (this.isManagedByApp) {
                return false;
            }

            return this.isNewWebhook
                ? this.acl.can('frosh_tools_webhook.creator')
                : this.acl.can('frosh_tools_webhook.editor');
        },

        tooltipSave() {
            if (!this.allowSave) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();
            return { message: `${systemKey} + S`, appearance: 'light' };
        },

        eventLogColumns() {
            return [
                {
                    property: 'deliveryStatus',
                    label: 'frosh-tools-webhook.detail.eventLog.columnStatus',
                },
                {
                    property: 'eventName',
                    label: 'frosh-tools-webhook.detail.eventLog.columnEventName',
                },
                {
                    property: 'url',
                    label: 'frosh-tools-webhook.detail.eventLog.columnUrl',
                },
                {
                    property: 'responseStatusCode',
                    label: 'frosh-tools-webhook.detail.eventLog.columnResponseStatusCode',
                    align: 'right',
                },
                {
                    property: 'processingTime',
                    label: 'frosh-tools-webhook.detail.eventLog.columnProcessingTime',
                    align: 'right',
                },
                {
                    property: 'createdAt',
                    label: 'frosh-tools-webhook.detail.eventLog.columnTimestamp',
                },
            ];
        },

        date() {
            return Shopware.Filter.getByName('date');
        },

        ...mapPropertyErrors('webhook', ['name', 'eventName', 'url']),
    },

    watch: {
        webhookId() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            if (this.webhookId) {
                const criteria = new Criteria();
                criteria.addAssociation('app');

                this.webhookRepository
                    .get(this.webhookId, Shopware.Context.api, criteria)
                    .then((webhook) => {
                        this.webhook = webhook;
                        this.isLoading = false;
                        this.loadEventLogs();
                    });
            } else {
                this.webhook = this.webhookRepository.create();
                this.isLoading = false;
            }
        },

        loadEventLogs() {
            if (!this.webhook || !this.webhook.name) {
                return;
            }

            this.eventLogsLoading = true;

            const criteria = new Criteria(
                this.eventLogsPage,
                this.eventLogsLimit
            );
            criteria.addFilter(
                Criteria.equals('webhookName', this.webhook.name)
            );
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            this.eventLogRepository
                .search(criteria)
                .then((result) => {
                    this.eventLogs = result;
                    this.eventLogsTotal = result.total;
                    this.eventLogsLoading = false;
                })
                .catch(() => {
                    this.eventLogsLoading = false;
                });
        },

        onEventLogPageChange({ page, limit }) {
            this.eventLogsPage = page;
            this.eventLogsLimit = limit;
            this.loadEventLogs();
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.webhookRepository
                .save(this.webhook)
                .then(() => {
                    this.isSaveSuccessful = true;

                    if (!this.webhookId) {
                        this.$router.push({
                            name: 'frosh.tools.webhook.detail',
                            params: { id: this.webhook.id },
                        });
                    }

                    const criteria = new Criteria();
                    criteria.addAssociation('app');

                    return this.webhookRepository
                        .get(this.webhook.id, Shopware.Context.api, criteria)
                        .then((updatedWebhook) => {
                            this.webhook = updatedWebhook;
                            this.isLoading = false;
                        });
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc(
                            'frosh-tools-webhook.detail.messageSaveError'
                        ),
                    });
                    this.isLoading = false;
                });
        },

        onCancel() {
            this.$router.push({ name: 'frosh.tools.webhook.index' });
        },
    },
});
