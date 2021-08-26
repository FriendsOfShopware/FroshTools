import template from './template.twig';
import './style.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('frosh-tools-tab-cache', {
    template,

    inject: ['FroshToolsService', 'repositoryFactory', 'themeService'],
    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            cacheInfo: null,
            isLoading: true,
            numberFormater: null
        }
    },

    async created() {
        const language = Shopware.Application.getContainer('factory').locale.getLastKnownLocale();
        this.numberFormater = new Intl.NumberFormat(
            language ?? navigator.language,
            { maximumFractionDigits: 2 }
        );

        this.createdComponent();
    },

    computed: {
        columns() {
            return [
                {
                    property: 'name',
                    label: 'frosh-tools.name',
                    rawData: true
                },
                {
                    property: 'size',
                    label: 'frosh-tools.used',
                    rawData: true
                },
                {
                    property: 'freeSpace',
                    label: 'frosh-tools.free',
                    rawData: true
                }
            ];
        },
        cacheFolders() {
            if (this.cacheInfo === null) {
                return [];
            }

            return this.cacheInfo;
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        }
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;
            this.cacheInfo = await this.FroshToolsService.getCacheInfo();
            this.isLoading = false;
        },

        formatSize(bytes) {
            bytes /= 1024 * 1024;

            return this.numberFormater.format(bytes) + ' MiB';
        },

        async clearCache(item) {
            this.isLoading = true;
            await this.FroshToolsService.clearCache(item.name);
            await this.createdComponent();
        },

        async compileTheme() {
            const criteria = new Criteria();
            criteria.addAssociation('themes');
            this.isLoading = true;

            let salesChannels = await this.salesChannelRepository.search(criteria, Shopware.Context.api);

            for (let salesChannel of salesChannels) {
                const theme = salesChannel.extensions.themes.first();

                await this.themeService.assignTheme(theme.id, salesChannel.id);
                this.createNotificationSuccess({
                    message: `${salesChannel.translated.name}` + ': ' + this.$tc('frosh-tools.themeCompiled')
                })
            }

            this.isLoading = false;
        }
    }
});
