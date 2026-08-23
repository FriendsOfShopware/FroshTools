import template from './template.twig';
import './style.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('frosh-tools-tab-scheduled', {
    template,
    inject: {
        repositoryFactory: { from: 'repositoryFactory' },
        froshToolsService: { from: 'froshToolsService' },
        froshToolsSearch: { default: null },
    },
    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('frosh-sortable-table'),
    ],

    data() {
        return {
            items: null,
            schedules: null,
            isLoading: true,
            loadError: null,
            taskError: null,
            openMenuId: null,
            editTask: null,
            editForm: {
                runInterval: 60,
                nextExecutionTime: null,
            },
            isSaving: false,
        };
    },

    created() {
        this.createdComponent();
        document.addEventListener('click', this.closeMenu);
        document.addEventListener('keydown', this.onMenuKeydown);
    },

    unmounted() {
        document.removeEventListener('click', this.closeMenu);
        document.removeEventListener('keydown', this.onMenuKeydown);
    },

    computed: {
        searchTerm() {
            return this.froshToolsSearch?.searchTerm ?? '';
        },

        visibleItems() {
            return this.filterRows(this.items, this.searchTerm, ['name']);
        },

        scheduledRepository() {
            return this.repositoryFactory.create('scheduled_task');
        },

        dateFilter() {
            return Shopware.Filter.getByName('date');
        },

        visibleSchedules() {
            if (!Array.isArray(this.schedules)) return [];

            return this.schedules.filter(
                (schedule) =>
                    schedule.error ||
                    this.filterScheduleMessages(schedule).length > 0
            );
        },
    },

    methods: {
        filterScheduleMessages(schedule) {
            return this.filterRows(schedule.messages || [], this.searchTerm, [
                'label',
                'messageClass',
                'trigger',
            ]);
        },

        sortScheduleMessages(schedule) {
            return this.sortRows(
                this.filterScheduleMessages(schedule),
                `symfony-${schedule.name}`
            );
        },

        shortName(fqn) {
            if (!fqn) return '';
            return fqn.split('\\').pop();
        },

        formatDate(value) {
            if (!value) return '—';
            return this.dateFilter(value, {
                hour: '2-digit',
                minute: '2-digit',
            });
        },

        statusVariant(status) {
            switch ((status || '').toLowerCase()) {
                case 'scheduled':
                    return 'success';
                case 'queued':
                    return 'warning';
                case 'running':
                    return 'info';
                case 'failed':
                    return 'danger';
                default:
                    return 'muted';
            }
        },

        toggleMenu(id) {
            this.openMenuId = this.openMenuId === id ? null : id;
        },

        closeMenu() {
            this.openMenuId = null;
        },

        onMenuKeydown(event) {
            if (event.key === 'Escape' && this.openMenuId !== null) {
                this.closeMenu();
            }
        },

        onMenu(action, item) {
            this.openMenuId = null;
            switch (action) {
                case 'edit':
                    return this.openEdit(item);
                case 'run':
                    return this.runTask(item);
                case 'schedule':
                    return this.scheduleTask(item, false);
                case 'schedule-immediate':
                    return this.scheduleTask(item, true);
                case 'deactivate':
                    return this.deactivateTask(item);
            }
        },

        openEdit(item) {
            this.editTask = item;
            this.editForm = {
                runInterval: item.runInterval,
                nextExecutionTime: item.nextExecutionTime,
            };
        },

        closeEdit() {
            this.editTask = null;
        },

        async saveEdit() {
            if (!this.editTask) return;
            this.isSaving = true;
            try {
                this.editTask.runInterval = parseInt(
                    this.editForm.runInterval,
                    10
                );
                this.editTask.nextExecutionTime =
                    this.editForm.nextExecutionTime;
                await this.scheduledRepository.save(
                    this.editTask,
                    Shopware.Context.api
                );
                this.createNotificationSuccess({
                    message: this.$t('global.default.success'),
                });
                this.editTask = null;
                await this.createdComponent();
            } catch (e) {
                this.createNotificationError({
                    message: this.$t('global.default.error'),
                });
                this.taskError = e.response?.data || String(e);
            } finally {
                this.isSaving = false;
            }
        },

        async refresh() {
            await this.createdComponent();
        },

        async createdComponent() {
            this.isLoading = true;
            this.loadError = null;

            const criteria = new Criteria(1, 500);
            criteria.addSorting(Criteria.sort('nextExecutionTime', 'ASC'));

            const [tasks, schedules] = await Promise.allSettled([
                this.scheduledRepository.search(criteria, Shopware.Context.api),
                this.loadSymfonySchedules(),
            ]);

            if (tasks.status === 'fulfilled') {
                this.items = tasks.value;
            } else {
                const error = tasks.reason;
                this.items = null;
                this.loadError = error?.response?.data?.error ?? error.message;
                this.createNotificationError({ message: this.loadError });
            }

            // A failing scheduler lookup must not blank the Shopware task table,
            // so it only drops the additional panels.
            this.schedules =
                schedules.status === 'fulfilled' ? schedules.value : null;

            this.isLoading = false;
        },

        async loadSymfonySchedules() {
            const schedules =
                await this.froshToolsService.getSymfonySchedules();

            return Array.isArray(schedules) ? schedules : null;
        },

        async runSymfonyTask(item) {
            this.isLoading = true;
            try {
                this.createNotificationInfo({
                    message: this.$t('frosh-tools.scheduledTaskStarted', {
                        name: item.label,
                    }),
                });
                await this.froshToolsService.runSymfonySchedulerTask(
                    item.scheduleName,
                    item.id
                );
                this.createNotificationSuccess({
                    message: this.$t(
                        'frosh-tools.symfonyScheduler.dispatched',
                        { name: item.label }
                    ),
                });
            } catch (e) {
                this.createNotificationError({
                    message: this.$t('frosh-tools.scheduledTaskFailed', {
                        name: item.label,
                    }),
                });
                this.taskError = e.response?.data;
            }
            this.createdComponent();
        },

        async runTask(item) {
            this.isLoading = true;
            try {
                this.createNotificationInfo({
                    message: this.$t('frosh-tools.scheduledTaskStarted', {
                        name: item.name,
                    }),
                });
                await this.froshToolsService.runScheduledTask(item.id);
                this.createNotificationSuccess({
                    message: this.$t('frosh-tools.scheduledTaskSucceed', {
                        name: item.name,
                    }),
                });
            } catch (e) {
                this.createNotificationError({
                    message: this.$t('frosh-tools.scheduledTaskFailed', {
                        name: item.name,
                    }),
                });
                this.taskError = e.response?.data;
            }
            this.createdComponent();
        },

        async scheduleTask(item, immediately = false) {
            this.isLoading = true;
            try {
                this.createNotificationInfo({
                    message: this.$t(
                        'frosh-tools.scheduledTaskScheduleStarted',
                        { name: item.name }
                    ),
                });
                await this.froshToolsService.scheduleScheduledTask(
                    item.id,
                    immediately
                );
                this.createNotificationSuccess({
                    message: this.$t(
                        'frosh-tools.scheduledTaskScheduleSucceed',
                        { name: item.name }
                    ),
                });
            } catch (e) {
                this.createNotificationError({
                    message: this.$t(
                        'frosh-tools.scheduledTaskScheduleFailed',
                        { name: item.name }
                    ),
                });
                this.taskError = e.response?.data;
            }
            this.createdComponent();
        },

        async deactivateTask(item) {
            this.isLoading = true;
            try {
                this.createNotificationInfo({
                    message: this.$t(
                        'frosh-tools.scheduledTaskDeactivateStarted',
                        { name: item.name }
                    ),
                });
                await this.froshToolsService.deactivateScheduledTask(item.id);
                this.createNotificationSuccess({
                    message: this.$t(
                        'frosh-tools.scheduledTaskDeactivateSucceed',
                        { name: item.name }
                    ),
                });
            } catch (e) {
                this.createNotificationError({
                    message: this.$t(
                        'frosh-tools.scheduledTaskDeactivateFailed',
                        { name: item.name }
                    ),
                });
                this.taskError = e.response?.data;
            }
            this.createdComponent();
        },

        async registerScheduledTasks() {
            this.isLoading = true;
            try {
                this.createNotificationInfo({
                    message: this.$t(
                        'frosh-tools.scheduledTasksRegisterStarted'
                    ),
                });
                await this.froshToolsService.scheduledTasksRegister();
                this.createNotificationSuccess({
                    message: this.$t(
                        'frosh-tools.scheduledTasksRegisterSucceed'
                    ),
                });
            } catch (e) {
                this.createNotificationError({
                    message: this.$t(
                        'frosh-tools.scheduledTasksRegisterFailed'
                    ),
                });
                this.taskError = e.response?.data;
            }
            this.createdComponent();
        },
    },
});
