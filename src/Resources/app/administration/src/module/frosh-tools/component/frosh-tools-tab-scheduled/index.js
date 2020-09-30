import template from './template.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('frosh-tools-tab-scheduled', {
    template,
    inject: ['repositoryFactory', 'FroshToolsService'],
    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            items: null,
            showResetModal: false,
            isLoading: true
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        scheduledRepository() {
            return this.repositoryFactory.create('scheduled_task');
        },

        columns() {
            return [
                {
                    property: 'name',
                    label: 'Name',
                    rawData: true,
                    primary: true
                },
                {
                    property: 'runInterval',
                    label: 'Interval',
                    rawData: true,
                    inlineEdit: 'number'
                },
                {
                    property: 'lastExecutionTime',
                    label: 'Last Execution Time',
                    rawData: true
                },
                {
                    property: 'nextExecutionTime',
                    label: 'Next Execution Time',
                    rawData: true,
                    inlineEdit: 'datetime'
                }
            ];
        }
    },

    methods: {
        async createdComponent() {
            const criteria = new Criteria;
            this.items = await this.scheduledRepository.search(criteria, Shopware.Context.api);
            this.isLoading = false;
        },
        async runTask(item) {
            this.isLoading = true;

            try {
                this.createNotificationInfo({
                    message: `The scheduled task execution for ${item.name} started`
                })
                await this.FroshToolsService.runScheduledTask(item.id);
                this.createNotificationSuccess({
                    message: `The scheduled task execution for ${item.name} succeed`
                })
            } catch (e) {
                this.createNotificationError({
                    message: `The scheduled task execution for ${item.name} failed`
                })
            }

            this.createdComponent();
        }
    }
});
