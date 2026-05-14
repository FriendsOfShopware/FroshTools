import template from './template.twig';
import './style.scss';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-logs', {
    template,
    inject: ['froshToolsService'],
    mixins: [Mixin.getByName('notification')],

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
        levelPill(level) {
            const l = (level || '').toLowerCase();
            if (['emergency', 'alert', 'critical'].includes(l)) return 'ft-pill--danger';
            if (l === 'error') return 'ft-pill--danger';
            if (l === 'warning' || l === 'notice') return 'ft-pill--warning';
            if (l === 'info') return 'ft-pill--info';
            if (l === 'debug') return 'ft-pill--muted';
            return 'ft-pill--muted';
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
                this.limit,
            );
            this.logEntries = logEntries.data;
            this.totalLogEntries = parseInt(logEntries.headers['file-size'], 10);
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
