import template from './template.twig';
import './style.scss';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-queue', {
    template,
    inject: ['repositoryFactory', 'froshToolsService', 'acl'],
    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('frosh-sortable-table'),
    ],

    data() {
        return {
            transports: [],
            showResetModal: false,
            purgeTransportCandidate: null,
            isLoading: true,
            browseTransport: null,
            browseLimit: 10,
            browseLoading: false,
            browseMessages: null,
            browseError: null,
            expandedMessages: {},
            messageActionsBusy: false,
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async refresh() {
            this.isLoading = true;
            await this.createdComponent();
        },
        async createdComponent() {
            this.transports = await this.froshToolsService.getQueueTransports();
            this.isLoading = false;
        },
        async resetQueue() {
            this.isLoading = true;
            await this.froshToolsService.resetQueue();
            this.showResetModal = false;
            await this.createdComponent();
            this.createNotificationSuccess({
                message: this.$t('frosh-tools.tabs.queue.reset.success'),
            });
            this.isLoading = false;
        },
        openBrowseModal(transport) {
            if (!transport.browsable) {
                return;
            }

            this.browseTransport = transport;
            this.browseMessages = null;
            this.browseError = null;
            this.expandedMessages = {};
            this.fetchMessages();
        },
        closeBrowseModal() {
            this.browseTransport = null;
            this.browseMessages = null;
            this.browseError = null;
        },
        async fetchMessages() {
            if (!this.browseTransport) {
                return;
            }

            this.browseLoading = true;
            this.browseError = null;
            this.expandedMessages = {};

            try {
                const limit = Math.min(
                    100,
                    Math.max(1, Number(this.browseLimit) || 10)
                );
                this.browseLimit = limit;
                const result = await this.froshToolsService.getQueueMessages(
                    this.browseTransport.name,
                    limit
                );
                this.browseMessages = result.messages;
            } catch (error) {
                this.browseMessages = null;
                this.browseError =
                    error?.response?.data?.error ?? error.message;
            } finally {
                this.browseLoading = false;
            }
        },
        toggleMessage(index) {
            this.expandedMessages = {
                ...this.expandedMessages,
                [index]: !this.expandedMessages[index],
            };
        },
        async retryMessage(message) {
            this.messageActionsBusy = true;
            try {
                await this.froshToolsService.retryQueueMessage(
                    this.browseTransport.name,
                    message.id
                );
                this.createNotificationSuccess({
                    message: this.$t(
                        'frosh-tools.tabs.queue.browse.retrySuccess',
                        {
                            transport: message.originalTransport,
                        }
                    ),
                });
                await this.afterMessageAction();
            } catch (error) {
                this.createNotificationError({
                    message: error?.response?.data?.error ?? error.message,
                });
            } finally {
                this.messageActionsBusy = false;
            }
        },
        async deleteMessage(message) {
            this.messageActionsBusy = true;
            try {
                await this.froshToolsService.deleteQueueMessage(
                    this.browseTransport.name,
                    message.id
                );
                this.createNotificationSuccess({
                    message: this.$t(
                        'frosh-tools.tabs.queue.browse.deleteSuccess'
                    ),
                });
                await this.afterMessageAction();
            } catch (error) {
                this.createNotificationError({
                    message: error?.response?.data?.error ?? error.message,
                });
            } finally {
                this.messageActionsBusy = false;
            }
        },
        async afterMessageAction() {
            await Promise.all([this.fetchMessages(), this.refreshTransports()]);
        },
        async refreshTransports() {
            this.transports = await this.froshToolsService.getQueueTransports();
            if (this.browseTransport) {
                this.browseTransport =
                    this.transports.find(
                        (transport) =>
                            transport.name === this.browseTransport.name
                    ) ?? this.browseTransport;
            }
        },
        askPurgeTransport(transport) {
            this.purgeTransportCandidate = transport;
        },
        async purgeTransport() {
            const transport = this.purgeTransportCandidate;
            this.purgeTransportCandidate = null;
            this.isLoading = true;
            try {
                await this.froshToolsService.purgeQueueTransport(
                    transport.name
                );
                this.createNotificationSuccess({
                    message: this.$t(
                        'frosh-tools.tabs.queue.transports.purgeSuccess',
                        {
                            name: transport.name,
                        }
                    ),
                });
            } catch (error) {
                this.createNotificationError({
                    message: error?.response?.data?.error ?? error.message,
                });
            }
            await this.createdComponent();
        },
        formatAge(seconds) {
            if (seconds === null || seconds === undefined) {
                return '–';
            }
            if (seconds < 60) {
                return `${seconds}s`;
            }
            if (seconds < 3600) {
                return `${Math.floor(seconds / 60)}m`;
            }
            if (seconds < 86400) {
                return `${Math.floor(seconds / 3600)}h ${Math.floor((seconds % 3600) / 60)}m`;
            }
            return `${Math.floor(seconds / 86400)}d ${Math.floor((seconds % 86400) / 3600)}h`;
        },
        workerState(transport) {
            if (
                transport.workerLastSeenSeconds === null ||
                transport.workerLastSeenSeconds === undefined
            ) {
                return 'unknown';
            }
            return transport.workerLastSeenSeconds <= 120 ? 'active' : 'stale';
        },
        shortClassName(className) {
            const parts = String(className).split('\\');
            return parts[parts.length - 1];
        },
        formatBody(body) {
            if (typeof body === 'string') {
                return body;
            }

            return JSON.stringify(body, null, 2);
        },
    },
});
