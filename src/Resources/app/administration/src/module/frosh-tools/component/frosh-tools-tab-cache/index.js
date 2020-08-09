import template from './template.twig';

const { Component } = Shopware;

Component.register('frosh-tools-tab-cache', {
    template,

    inject: ['FroshToolsService'],

    data() {
        return {
            cacheInfo: null,
            isLoading: true
        }
    },

    async created() {
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
                    label: 'Größe',
                    rawData: true
                }
            ];
        },
        cacheFolders() {
            if (this.cacheInfo === null) {
                return [];
            }

            return this.cacheInfo;
        }
    },

    methods: {
        async createdComponent() {
            this.cacheInfo = await this.FroshToolsService.getCacheInfo();
            this.isLoading = false;
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

        async deleteCache(item) {
            this.isLoading = true;
            await this.FroshToolsService.clearCache(item.name);
            await this.createdComponent();
        }
    }
});
