import './select';
import template from './template.twig';
import './style.scss';

type ActivityEntry = {
    id: string;
    action: string;
    createdAt: string | null;
    level: number;
    context: Record<string, string | number | boolean | string[]>;
};

Shopware.Component.register('frosh-tools-security-timeline', {
    template,
    inject: ['froshToolsService'],

    data() {
        return {
            entries: [] as ActivityEntry[],
            total: 0,
            page: 1,
            limit: 25,
            actor: null as string | null,
            action: null as string | null,
            actorValues: [] as string[],
            actionValues: [] as string[],
            optionsLoading: { actor: false, action: false },
            optionsError: { actor: false, action: false },
            optionsRequestId: { actor: 0, action: 0 },
            moreActors: false,
            moreActions: false,
            actionPage: 1,
            clientIp: '',
            userId: '',
            subjectType: '',
            subjectId: '',
            period: 'all',
            from: '',
            to: '',
            filters: {
                actor: '',
                action: '',
                from: '',
                to: '',
                clientIp: '',
                userId: '',
                subjectType: '',
                subjectId: '',
            },
            isExporting: false,
            exportError: false,
            isLoading: false,
            error: false,
            requestId: 0,
            selected: null as ActivityEntry | null,
        };
    },

    created() {
        this.readRouteSubject();
        void this.load();
        void this.loadOptions('actor');
        void this.loadOptions('action');
    },

    watch: {
        '$route.query': {
            deep: true,
            handler() {
                this.readRouteSubject();
                this.applyFilters();
            },
        },
    },

    computed: {
        periodOptions() {
            return ['all', 'today', '7days', '30days', 'custom'].map(
                (value) => ({
                    value,
                    label: this.$t(
                        `frosh-tools.tabs.security.timeline.periods.${value}`
                    ),
                })
            );
        },
        actorOptions() {
            const values = [
                ...new Set([
                    ...(this.actor ? [this.actor] : []),
                    ...this.actorValues,
                ]),
            ];
            return values.map((value) => ({ value, label: value }));
        },
        actionOptions() {
            return this.actionValues.map((value) => ({
                value,
                label: this.actionLabel(value),
            }));
        },
    },

    beforeUnmount() {
        this.requestId += 1;
        this.optionsRequestId.actor += 1;
        this.optionsRequestId.action += 1;
    },

    methods: {
        readRouteSubject() {
            const query = this.$route?.query || {};
            const validId = (value: unknown): value is string =>
                typeof value === 'string' && /^[0-9a-f]{32}$/i.test(value);
            this.userId = validId(query.userId)
                ? query.userId.toLowerCase()
                : '';
            this.subjectType =
                ['users', 'integrations', 'keys'].includes(query.subjectType) &&
                validId(query.subjectId)
                    ? query.subjectType
                    : '';
            this.subjectId = this.subjectType
                ? query.subjectId.toLowerCase()
                : '';
            this.filters.userId = this.userId;
            this.filters.subjectType = this.subjectType;
            this.filters.subjectId = this.subjectId;
        },
        clearIdentityFilters() {
            this.userId = '';
            this.subjectType = '';
            this.subjectId = '';
            const query = { ...(this.$route?.query || {}) };
            delete query.userId;
            delete query.subjectType;
            delete query.subjectId;
            this.$router?.replace({ query }).catch(() => {});
        },
        async exportActivity() {
            if (this.isExporting) return;
            this.isExporting = true;
            this.exportError = false;
            try {
                const response =
                    await this.froshToolsService.exportSecurityActivity({
                        ...this.filters,
                        exact: true,
                    });
                const blob =
                    response.data instanceof Blob
                        ? response.data
                        : new Blob([response.data], {
                              type: 'application/json',
                          });
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                try {
                    link.href = url;
                    link.download = 'security-activity.json';
                    document.body.appendChild(link);
                    link.click();
                } finally {
                    link.remove();
                    window.URL.revokeObjectURL(url);
                }
            } catch {
                this.exportError = true;
            } finally {
                this.isExporting = false;
            }
        },

        async load() {
            const requestId = ++this.requestId;
            this.isLoading = true;
            this.error = false;
            try {
                const result = await this.froshToolsService.getSecurityActivity(
                    {
                        ...this.filters,
                        exact: true,
                        page: this.page,
                        limit: this.limit,
                    }
                );
                if (requestId !== this.requestId) return;
                this.entries = result.entries;
                this.total = result.total;
            } catch {
                if (requestId !== this.requestId) return;
                this.entries = [];
                this.total = 0;
                this.error = true;
            } finally {
                if (requestId === this.requestId) this.isLoading = false;
            }
        },

        selectPeriod(value: string | null) {
            value = value || 'all';
            this.period = value;
            if (value === 'custom') return;
            this.from = '';
            this.to = '';
            if (value !== 'all') {
                const now = new Date();
                this.to = now.toISOString().slice(0, 10);
                const days =
                    value === '7days' ? 6 : value === '30days' ? 29 : 0;
                now.setUTCDate(now.getUTCDate() - days);
                this.from = now.toISOString().slice(0, 10);
            }
            this.applyFilters();
        },

        clearUser() {
            this.userId = '';
            if (!this.$route?.query?.userId) return;
            const query = { ...(this.$route?.query || {}) };
            delete query.userId;
            this.$router?.replace({ query }).catch(() => {});
        },

        applyFilters() {
            this.filters = {
                clientIp: this.clientIp.trim(),
                userId: this.userId,
                subjectType: this.subjectType,
                subjectId: this.subjectId,
                actor: this.actor || '',
                action: this.action || '',
                from: this.from,
                to: this.to,
            };
            this.page = 1;
            void this.load();
        },

        async loadOptions(field: 'actor' | 'action', term = '', page = 1) {
            const requestId = ++this.optionsRequestId[field];
            this.optionsLoading[field] = true;
            this.optionsError[field] = false;
            try {
                const result =
                    await this.froshToolsService.getSecurityActivityOptions(
                        field,
                        term.slice(0, 100),
                        page
                    );
                if (requestId !== this.optionsRequestId[field]) return;
                if (field === 'actor') {
                    this.actorValues = result.values;
                    this.moreActors = result.hasMore;
                } else {
                    this.actionValues =
                        page === 1
                            ? result.values
                            : [...this.actionValues, ...result.values];
                    this.actionPage = page;
                    this.moreActions = result.hasMore;
                }
            } catch {
                if (requestId === this.optionsRequestId[field])
                    this.optionsError[field] = true;
            } finally {
                if (requestId === this.optionsRequestId[field])
                    this.optionsLoading[field] = false;
            }
        },

        loadMoreActions() {
            if (this.moreActions && !this.optionsLoading.action) {
                void this.loadOptions('action', '', this.actionPage + 1);
            }
        },

        retryOptions() {
            void this.loadOptions('actor');
            void this.loadOptions('action');
        },

        resetFilters() {
            this.clearIdentityFilters();
            this.clientIp = '';
            this.period = 'all';
            this.actor = null;
            this.action = null;
            this.from = '';
            this.to = '';
            this.applyFilters();
            void this.loadOptions('actor');
            void this.loadOptions('action');
        },

        actionLabel(action: string) {
            const key = `frosh-tools.tabs.security.timeline.actions.${action}`;
            return this.$te(key) ? this.$t(key) : action;
        },

        changePage({ page, limit }: { page: number; limit: number }) {
            this.page = page;
            this.limit = limit;
            void this.load();
        },

        actorLabel(entry: ActivityEntry) {
            const context = entry.context;
            return (
                context.username ||
                context.loginUsername ||
                context.userId ||
                context.integrationId ||
                context.integrationAccessKey ||
                context.actorType ||
                '—'
            );
        },

        targetLabel(entry: ActivityEntry) {
            return (
                entry.context.pluginName ||
                entry.context.appName ||
                entry.context.targetUserId ||
                entry.context.entityId ||
                entry.context.appId ||
                '—'
            );
        },

        formatDate(value: string | null) {
            return value
                ? Shopware.Filter.getByName('date')(value, {
                      hour: '2-digit',
                      minute: '2-digit',
                      second: '2-digit',
                  })
                : '—';
        },

        details(entry: ActivityEntry) {
            return JSON.stringify(entry.context, null, 2);
        },
    },
});
