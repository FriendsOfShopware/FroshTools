import template from './template.twig';

const { Component } = Shopware;

Component.register('frosh-tools-tab-elasticsearch', {
    template,

    inject: ['froshElasticSearch'],

    data() {
        return {
            isLoading: true,
            isActive: true,
            statusInfo: {},
            indices: [],
            consoleInput: 'GET /_cat/indices'
        };
    },

    computed: {
        columns() {
            return [
                {
                    property: 'name',
                    label: 'frosh-tools.name',
                    rawData: true,
                    primary: true
                },
                {
                    property: 'indexSize',
                    label: 'frosh-tools.size',
                    rawData: true,
                    primary: true
                },
                {
                    property: 'docs',
                    label: 'frosh-tools.docs',
                    rawData: true,
                    primary: true
                }
            ];
        }
    },

    async created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;

            try {
                this.statusInfo = await this.froshElasticSearch.status();
            } catch (err) {
                this.isActive = false;
                this.isLoading = false;

                return;
            } finally {
                this.isLoading = false;
            }

            this.indices = await this.froshElasticSearch.indices();
        },

        formatSize(bytes) {
            const thresh = 1024;
            const dp = 1;

            if (Math.abs(bytes) < thresh) {
                return bytes + ' B';
            }

            const units = ['KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
            let u = -1;
            const r = 10**dp;

            do {
                bytes /= thresh;
                ++u;
            } while (Math.round(Math.abs(bytes) * r) / r >= thresh && u < units.length - 1);

            return bytes.toFixed(dp) + ' ' + units[u];
        },

        async deleteIndex(indexName) {
            await this.froshElasticSearch.deleteIndex(indexName);
            await this.createdComponent();
        }
    }
})
