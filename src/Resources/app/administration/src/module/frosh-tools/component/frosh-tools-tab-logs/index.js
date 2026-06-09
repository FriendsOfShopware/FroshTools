import template from './template.twig';
import './style.scss';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-logs', {
    template,
    inject: ['froshToolsService'],
    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('frosh-sortable-table'),
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
            displayedLog: null,
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        date() {
            return Shopware.Filter.getByName('date');
        },
    },

    methods: {
        levelVariant(level) {
            const l = (level || '').toLowerCase();
            if (['emergency', 'alert', 'critical', 'error'].includes(l))
                return 'danger';
            if (l === 'warning' || l === 'notice') return 'warning';
            if (l === 'info') return 'info';
            return 'muted';
        },

        truncate(text) {
            if (!text) return '';
            return text.length > 220 ? `${text.slice(0, 220)}…` : text;
        },

        async refresh() {
            this.isLoading = true;
            await this.createdComponent();
            await this.loadLogEntries();
        },

        async createdComponent() {
            this.logFiles = await this.froshToolsService.getLogFiles();
            this.isLoading = false;
        },

        async onFileSelected() {
            this.page = 1;
            await this.loadLogEntries();
        },

        async loadLogEntries() {
            if (!this.selectedLogFile) {
                return;
            }
            const logEntries = await this.froshToolsService.getLogFile(
                this.selectedLogFile,
                (this.page - 1) * this.limit,
                this.limit
            );
            this.logEntries = logEntries.data;
            this.totalLogEntries = parseInt(
                logEntries.headers['file-size'],
                10
            );
        },

        async onPageChange(page) {
            this.page = page.page;
            this.limit = page.limit;
            await this.loadLogEntries();
        },

        showInfoModal(entryContents) {
            this.displayedLog = entryContents;
        },

        closeInfoModal() {
            this.displayedLog = null;
        },
    },
});
