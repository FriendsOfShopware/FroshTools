import template from './template.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('frosh-tools-tab-queue', {
    template,
    inject: ['repositoryFactory', 'FroshToolsService'],
    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            queueEntries: null,
            showResetModal: false
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
                    property: 'name',
                    label: 'Name',
                    rawData: true
                },
                {
                    property: 'size',
                    label: 'Size',
                    rawData: true
                }
            ];
        }
    },

    methods: {
        async createdComponent() {
            const criteria = new Criteria;
            criteria.addSorting(Criteria.sort('size', 'DESC'))
            this.queueEntries = await this.queueRepository.search(criteria, Shopware.Context.api);

            for (let queue of this.queueEntries) {
                let nameSplit = queue.name.split('\\')
                queue.name = nameSplit[nameSplit.length - 1];
            }
        },
        async resetQueue() {
            await this.FroshToolsService.deleteQueue();
            this.showResetModal = false;
            this.createdComponent();
            this.createNotificationSuccess({
                message: 'The queue has been cleared'
            })
        }
    }
});
