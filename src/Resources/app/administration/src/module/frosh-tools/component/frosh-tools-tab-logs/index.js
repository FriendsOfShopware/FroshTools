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

        formatMessage(message) {
            if (!message) return '';
            const parts = [];
            let i = 0;
            let textStart = 0;
            const s = message;
            while (i < s.length) {
                const c = s[i];
                if (c !== '{' && c !== '[') {
                    i++;
                    continue;
                }
                let depth = 0;
                let inString = false;
                let escape = false;
                let end = -1;
                for (let j = i; j < s.length; j++) {
                    const ch = s[j];
                    if (escape) { escape = false; continue; }
                    if (ch === '\\' && inString) { escape = true; continue; }
                    if (ch === '"') { inString = !inString; continue; }
                    if (!inString) {
                        if (ch === '{' || ch === '[') depth++;
                        else if (ch === '}' || ch === ']') {
                            depth--;
                            if (depth === 0) { end = j; break; }
                        }
                    }
                }
                if (end < 0) { i++; continue; }
                const candidate = s.slice(i, end + 1);
                try {
                    const parsed = JSON.parse(candidate);
                    parts.push(s.slice(textStart, i));
                    parts.push('\n' + JSON.stringify(parsed, null, 2) + '\n');
                    textStart = end + 1;
                    i = end + 1;
                } catch {
                    i++;
                }
            }
            parts.push(s.slice(textStart));
            return parts.join('');
        },

        showInfoModal(entryContents) {
            this.displayedLog = entryContents;
        },

        closeInfoModal() {
            this.displayedLog = null;
        },
    },
});
