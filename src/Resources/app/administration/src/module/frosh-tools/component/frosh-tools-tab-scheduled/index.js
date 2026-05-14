import template from './template.twig';
import './style.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('frosh-tools-tab-scheduled', {
    template,
    inject: ['repositoryFactory', 'froshToolsService'],
    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            items: null,
            isLoading: true,
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
    },

    unmounted() {
        document.removeEventListener('click', this.closeMenu);
    },

    computed: {
        scheduledRepository() {
            return this.repositoryFactory.create('scheduled_task');
        },

        dateFilter() {
            return Shopware.Filter.getByName('date');
        },
    },

    methods: {
        shortName(fqn) {
            if (!fqn) return '';
            return fqn.split('\\').pop();
        },

        formatDate(value) {
            if (!value) return '—';
            return this.dateFilter(value, { hour: '2-digit', minute: '2-digit' });
        },

        statusPill(status) {
            switch ((status || '').toLowerCase()) {
                case 'scheduled': return 'ft-pill--success';
                case 'queued':    return 'ft-pill--warning';
                case 'running':   return 'ft-pill--info';
                case 'failed':    return 'ft-pill--danger';
                case 'inactive':  return 'ft-pill--muted';
                default:          return 'ft-pill--muted';
            }
        },

        countByStatus(status) {
            if (!this.items) return 0;
            return this.items.filter((i) => (i.status || '').toLowerCase() === status).length;
        },

        toggleMenu(id) {
            this.openMenuId = this.openMenuId === id ? null : id;
        },

        closeMenu() {
            this.openMenuId = null;
        },

        onMenu(action, item) {
            this.openMenuId = null;
            switch (action) {
                case 'edit':                return this.openEdit(item);
                case 'run':                 return this.runTask(item);
                case 'schedule':            return this.scheduleTask(item, false);
                case 'schedule-immediate':  return this.scheduleTask(item, true);
                case 'deactivate':          return this.deactivateTask(item);
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
                this.editTask.runInterval = parseInt(this.editForm.runInterval, 10);
                this.editTask.nextExecutionTime = this.editForm.nextExecutionTime;
                await this.scheduledRepository.save(this.editTask, Shopware.Context.api);
                this.createNotificationSuccess({
                    message: this.$t('global.default.success'),
                });
                this.editTask = null;
                await this.createdComponent();
            } catch (e) {
                this.createNotificationError({ message: this.$t('global.default.error') });
                this.taskError = e.response?.data || String(e);
            } finally {
                this.isSaving = false;
            }
        },

        async refresh() {
            this.isLoading = true;
            await this.createdComponent();
        },

        async createdComponent() {
            const criteria = new Criteria(1, 500);
            criteria.addSorting(Criteria.sort('nextExecutionTime', 'ASC'));
            this.items = await this.scheduledRepository.search(criteria, Shopware.Context.api);
            this.isLoading = false;
        },

        async runTask(item) {
            this.isLoading = true;
            try {
                this.createNotificationInfo({ message: this.$t('frosh-tools.scheduledTaskStarted', { name: item.name }) });
                await this.froshToolsService.runScheduledTask(item.id);
                this.createNotificationSuccess({ message: this.$t('frosh-tools.scheduledTaskSucceed', { name: item.name }) });
            } catch (e) {
                this.createNotificationError({ message: this.$t('frosh-tools.scheduledTaskFailed', { name: item.name }) });
                this.taskError = e.response?.data;
            }
            this.createdComponent();
        },

        async scheduleTask(item, immediately = false) {
            this.isLoading = true;
            try {
                this.createNotificationInfo({ message: this.$t('frosh-tools.scheduledTaskScheduleStarted', { name: item.name }) });
                await this.froshToolsService.scheduleScheduledTask(item.id, immediately);
                this.createNotificationSuccess({ message: this.$t('frosh-tools.scheduledTaskScheduleSucceed', { name: item.name }) });
            } catch (e) {
                this.createNotificationError({ message: this.$t('frosh-tools.scheduledTaskScheduleFailed', { name: item.name }) });
                this.taskError = e.response?.data;
            }
            this.createdComponent();
        },

        async deactivateTask(item) {
            this.isLoading = true;
            try {
                this.createNotificationInfo({ message: this.$t('frosh-tools.scheduledTaskDeactivateStarted', { name: item.name }) });
                await this.froshToolsService.deactivateScheduledTask(item.id);
                this.createNotificationSuccess({ message: this.$t('frosh-tools.scheduledTaskDeactivateSucceed', { name: item.name }) });
            } catch (e) {
                this.createNotificationError({ message: this.$t('frosh-tools.scheduledTaskDeactivateFailed', { name: item.name }) });
                this.taskError = e.response?.data;
            }
            this.createdComponent();
        },

        async registerScheduledTasks() {
            this.isLoading = true;
            try {
                this.createNotificationInfo({ message: this.$t('frosh-tools.scheduledTasksRegisterStarted') });
                await this.froshToolsService.scheduledTasksRegister();
                this.createNotificationSuccess({ message: this.$t('frosh-tools.scheduledTasksRegisterSucceed') });
            } catch (e) {
                this.createNotificationError({ message: this.$t('frosh-tools.scheduledTasksRegisterFailed') });
                this.taskError = e.response?.data;
            }
            this.createdComponent();
        },
    },
});
