import template from './template.twig';
import './style.scss';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-logs', {
    template,
    inject: ['froshToolsService'],
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
            isLoading: true,
            displayedLog: null
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        columns() {
            return [
                {
                    property: 'date',
                    label: 'frosh-tools.date',
                    rawData: true
                },
                {
                    property: 'channel',
                    label: 'frosh-tools.channel',
                    rawData: true
                },
                {
                    property: 'level',
                    label: 'frosh-tools.level',
                    rawData: true
                },
                {
                    property: 'message',
                    label: 'frosh-tools.message',
                    rawData: true
                }
            ];
        }
    },

    methods: {
        async refresh() {
            this.isLoading = true;
            await this.createdComponent();
            await this.onFileSelected();
        },

        async createdComponent() {
            this.logFiles = await this.froshToolsService.getLogFiles();
            this.isLoading = false;
        },

        async onFileSelected() {
            if (!this.selectedLogFile) {
                return;
            }

            const logEntries = await this.froshToolsService.getLogFile(
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
        },

        showInfoModal(entryContents) {
            this.displayedLog = entryContents;
        },

        closeInfoModal() {
            this.displayedLog = null;
        },
    }
});
