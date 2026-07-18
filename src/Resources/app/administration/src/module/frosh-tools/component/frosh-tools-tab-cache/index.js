import template from './template.twig';
import './style.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('frosh-tools-tab-cache', {
    template,

    inject: ['froshToolsService', 'repositoryFactory', 'themeService'],
    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('frosh-sortable-table'),
    ],

    data() {
        return {
            cacheInfo: null,
            isLoading: true,
            loadError: null,
            numberFormater: null,
        };
    },

    created() {
        const language =
            Shopware.Application.getContainer(
                'factory'
            ).locale.getLastKnownLocale();
        this.numberFormater = new Intl.NumberFormat(language, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        this.createdComponent();
    },

    computed: {
        columns() {
            return [
                {
                    property: 'name',
                    label: 'frosh-tools.name',
                    rawData: true,
                },
                {
                    property: 'size',
                    label: 'frosh-tools.used',
                    rawData: true,
                    align: 'right',
                },
                {
                    property: 'freeSpace',
                    label: 'frosh-tools.free',
                    rawData: true,
                    align: 'right',
                },
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
        },
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;
            this.loadError = null;

            try {
                this.cacheInfo = await this.froshToolsService.getCacheInfo();
            } catch (error) {
                this.cacheInfo = null;
                this.loadError = error?.response?.data?.error ?? error.message;
                this.createNotificationError({ message: this.loadError });
            } finally {
                this.isLoading = false;
            }
        },

        formatSize(bytes) {
            const formatted = bytes / (1024 * 1024);

            return this.numberFormater.format(formatted) + ' MiB';
        },

        async clearCache(item) {
            this.isLoading = true;

            try {
                await this.froshToolsService.clearCache(item.name);
                this.createNotificationSuccess({
                    message: this.$t('frosh-tools.cacheCleared', {
                        name: item.name,
                    }),
                });
            } catch (error) {
                this.createNotificationError({
                    message: error?.response?.data?.error ?? error.message,
                });
            }

            await this.createdComponent();
        },

        async compileTheme() {
            const criteria = new Criteria();
            criteria.addAssociation('themes');
            this.isLoading = true;

            try {
                let salesChannels = await this.salesChannelRepository.search(
                    criteria,
                    Shopware.Context.api
                );

                for (let salesChannel of salesChannels) {
                    const theme = salesChannel.extensions.themes.first();

                    if (theme) {
                        await this.themeService.assignTheme(
                            theme.id,
                            salesChannel.id
                        );
                        this.createNotificationSuccess({
                            message: `${salesChannel.translated.name}: ${this.$t('frosh-tools.themeCompiled')}`,
                        });
                    }
                }
            } catch (error) {
                this.createNotificationError({
                    message: error?.response?.data?.error ?? error.message,
                });
            } finally {
                this.isLoading = false;
            }
        },

        async clearOPcache() {
            this.isLoading = true;

            try {
                await this.froshToolsService.clearOPcache();
                this.createNotificationSuccess({
                    message: this.$t('frosh-tools.clearedOpcache'),
                });
            } catch (error) {
                this.createNotificationError({
                    message: error?.response?.data?.error ?? error.message,
                });
            }

            await this.createdComponent();
        },
    },
});
