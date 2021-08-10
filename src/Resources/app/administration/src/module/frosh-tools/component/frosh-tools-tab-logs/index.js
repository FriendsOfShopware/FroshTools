import template from './template.twig';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-logs', {
    template,
    inject: ['FroshToolsService'],
    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            logFiles: [],
            selectedLogFile: null,
            logEntries: [],
            totalLogEntries: 0,
            limit: 25,
            page: 1,
            isLoading: true
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        queueRepository() {
            return this.repositoryFactory.create('message_queue_stats');
        },

        columns() {
            return [
                {
                    property: 'date',
                    label: 'Date',
                    rawData: true
                },
                {
                    property: 'channel',
                    label: 'Channel',
                    rawData: true
                },
                {
                    property: 'level',
                    label: 'Level',
                    rawData: true
                },
                {
                    property: 'message',
                    label: 'Message',
                    rawData: true
                }
            ];
        }
    },

    methods: {
        async createdComponent() {
            this.logFiles = await this.FroshToolsService.getLogFiles();
            this.isLoading = false;
        },

        async onFileSelected() {
            const logEntries = await this.FroshToolsService.getLogFile(
                this.selectedLogFile,
                (this.page - 1) * this.limit,
                this.limit
            );

            this.logEntries = logEntries.data;
            this.totalLogEntries = parseInt(logEntries.headers['file-size']);
        },

        async onPageChange(page) {
            this.page = page.page;
            this.limit = page.limit;
            await this.onFileSelected();
        }
    }
});
