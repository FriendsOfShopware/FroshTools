import template from './template.twig'
import './style.scss'

const { Component, Mixin } = Shopware
const { Criteria } = Shopware.Data

Component.register('frosh-tools-tab-scheduled', {
  template,
  inject: ['repositoryFactory', 'froshToolsService'],
  mixins: [Mixin.getByName('notification')],

  data() {
    return {
      items: null,
      showResetModal: false,
      isLoading: true,
      page: 1,
      limit: 25,
      taskError: null,
    }
  },

  created() {
    this.createdComponent()
  },

  computed: {
    scheduledRepository() {
      return this.repositoryFactory.create('scheduled_task')
    },

    columns() {
      return [
        {
          property: 'name',
          label: 'frosh-tools.name',
          rawData: true,
          primary: true,
        },
        {
          property: 'runInterval',
          label: 'frosh-tools.interval',
          rawData: true,
          inlineEdit: 'number',
        },
        {
          property: 'lastExecutionTime',
          label: 'frosh-tools.lastExecutionTime',
          rawData: true,
        },
        {
          property: 'nextExecutionTime',
          label: 'frosh-tools.nextExecutionTime',
          rawData: true,
          inlineEdit: 'datetime',
        },
        {
          property: 'status',
          label: 'frosh-tools.status',
          rawData: true,
        },
      ]
    },

    date() {
      return Shopware.Filter.getByName('date')
    },
  },

  methods: {
    async refresh() {
      this.isLoading = true
      await this.createdComponent()
    },

    async createdComponent() {
      const criteria = new Criteria(this.page, this.limit)
      criteria.addSorting(Criteria.sort('nextExecutionTime', 'ASC'))
      this.items = await this.scheduledRepository.search(
        criteria,
        Shopware.Context.api
      )
      this.isLoading = false
    },

    async runTask(item) {
      this.isLoading = true

      try {
        this.createNotificationInfo({
          message: this.$t('frosh-tools.scheduledTaskStarted', 0, {
            name: item.name,
          }),
        })
        await this.froshToolsService.runScheduledTask(item.id)
        this.createNotificationSuccess({
          message: this.$t('frosh-tools.scheduledTaskSucceed', 0, {
            name: item.name,
          }),
        })
      } catch (e) {
        this.createNotificationError({
          message: this.$t('frosh-tools.scheduledTaskFailed', 0, {
            name: item.name,
          }),
        })

        this.taskError = e.response.data
      }

      this.createdComponent()
    },

    async scheduleTask(item, immediately = false) {
      this.isLoading = true

      try {
        this.createNotificationInfo({
          message: this.$t('frosh-tools.scheduledTaskScheduleStarted', {
            name: item.name,
          }),
        })
        await this.froshToolsService.scheduleScheduledTask(item.id, immediately)
        this.createNotificationSuccess({
          message: this.$t('frosh-tools.scheduledTaskScheduleSucceed', {
            name: item.name,
          }),
        })
      } catch (e) {
        this.createNotificationError({
          message: this.$t('frosh-tools.scheduledTaskScheduleFailed', {
            name: item.name,
          }),
        })

        this.taskError = e.response.data
      }

      this.createdComponent()
    },

    async deactivateTask(item) {
      this.isLoading = true

      try {
        this.createNotificationInfo({
          message: this.$t('frosh-tools.scheduledTaskDeactivateStarted', {
            name: item.name,
          }),
        })
        await this.froshToolsService.deactivateScheduledTask(item.id)
        this.createNotificationSuccess({
          message: this.$t('frosh-tools.scheduledTaskDeactivateSucceed', {
            name: item.name,
          }),
        })
      } catch (e) {
        this.createNotificationError({
          message: this.$t('frosh-tools.scheduledTaskDeactivateFailed', {
            name: item.name,
          }),
        })

        this.taskError = e.response.data
      }

      this.createdComponent()
    },

    async registerScheduledTasks() {
      this.isLoading = true

      try {
        this.createNotificationInfo({
          message: this.$t('frosh-tools.scheduledTasksRegisterStarted'),
        })
        await this.froshToolsService.scheduledTasksRegister()
        this.createNotificationSuccess({
          message: this.$t('frosh-tools.scheduledTasksRegisterSucceed'),
        })
      } catch (e) {
        this.createNotificationError({
          message: this.$t('frosh-tools.scheduledTasksRegisterFailed'),
        })

        this.taskError = e.response.data
      }

      this.createdComponent()
    },
  },
})
