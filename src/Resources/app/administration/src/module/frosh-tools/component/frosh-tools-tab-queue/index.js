import template from './template.twig';
import './style.scss';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-queue', {
    template,
    inject: ['repositoryFactory', 'froshToolsService'],
    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            queueEntries: [],
            showResetModal: false,
            isLoading: true
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
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
        async refresh() {
            this.isLoading = true;
            await this.createdComponent();
        },
        async createdComponent() {
            this.queueEntries = await this.froshToolsService.getQueue();

            for (const queue of this.queueEntries) {
                const nameSplit = queue.name.split('\\')
                queue.name = nameSplit[nameSplit.length - 1];
            }
            this.isLoading = false;
        },
        async resetQueue() {
            this.isLoading = true;
            await this.froshToolsService.resetQueue();
            this.showResetModal = false;
            await this.createdComponent();
            this.createNotificationSuccess({
                message: this.$tc('frosh-tools.tabs.queue.reset.success')
            })
            this.isLoading = false;
        }
    }
});
