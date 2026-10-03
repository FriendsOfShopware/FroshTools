import template from './template.twig';
import './style.scss';
import { PRIVILEGE } from '../../acl/privileges';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-apps', {
    template,
    inject: ['froshToolsService', 'acl'],
    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: true,
            isChecking: false,
            isResetting: false,
            loadError: null,
            status: null,
            storeUser: undefined,
            shopIdVisible: false,
            revealedShopId: null,
            showVerifyShopIdModal: false,
            isRevealingShopId: false,
            shopIdRevealRequestId: 0,
            copiedField: null,
            showResetModal: false,
            showVerifyResetModal: false,
            resetKeepUserData: false,
        };
    },

    computed: {
        canUpdate() {
            return this.acl.can(PRIVILEGE.APPS_UPDATE);
        },

        apps() {
            return this.status?.apps ?? [];
        },

        reachability() {
            return this.status?.reachability ?? null;
        },

        reachabilityVariant() {
            switch (this.reachability?.status) {
                case 'pass':
                    return 'success';
                case 'soft_fail':
                    return 'warning';
                case 'hard_fail':
                    return 'danger';
                default:
                    return 'muted';
            }
        },

        reachabilityLabel() {
            return this.$t(
                `frosh-tools.tabs.apps.reachability.status.${this.reachability?.status ?? 'unknown'}`
            );
        },

        reachabilityCheckedAt() {
            if (!this.reachability?.checkedAt) {
                return null;
            }

            return this.formatDate(this.reachability.checkedAt);
        },

        store() {
            return this.status?.store ?? null;
        },

        shopId() {
            return this.revealedShopId;
        },

        shopIdDisplay() {
            if (!this.status?.hasShopId) {
                return this.$t('frosh-tools.tabs.apps.shopId.none');
            }

            return this.shopIdVisible ? this.shopId : '••••••••••••••••';
        },
    },

    created() {
        this.loadStatus();
    },

    methods: {
        hideShopId() {
            this.shopIdRevealRequestId += 1;
            this.isRevealingShopId = false;
            this.shopIdVisible = false;
            this.revealedShopId = null;
            this.copiedField = null;
            this.showVerifyShopIdModal = false;
        },

        onShowShopId() {
            if (this.shopIdVisible) {
                this.hideShopId();
                return;
            }

            this.showVerifyShopIdModal = true;
        },

        async onShopIdVerified() {
            this.showVerifyShopIdModal = false;
            this.isRevealingShopId = true;
            const requestId = ++this.shopIdRevealRequestId;
            try {
                const result = await this.froshToolsService.getAppsShopId();
                if (requestId !== this.shopIdRevealRequestId) {
                    return;
                }
                this.revealedShopId = result.shopId;
                this.shopIdVisible = !!result.shopId;
            } catch {
                if (requestId !== this.shopIdRevealRequestId) {
                    return;
                }
                this.hideShopId();
                this.createNotificationError({
                    message: this.$t(
                        'frosh-tools.tabs.apps.shopId.revealError'
                    ),
                });
            } finally {
                if (requestId === this.shopIdRevealRequestId) {
                    this.isRevealingShopId = false;
                }
            }
        },

        async loadStatus() {
            this.hideShopId();
            this.isLoading = true;
            this.loadError = null;
            try {
                this.status = await this.froshToolsService.getAppsStatus();
            } catch (error) {
                this.loadError =
                    error?.response?.data?.message ?? error?.message ?? '';
            } finally {
                this.isLoading = false;
            }

            this.loadStoreUserInfo();
        },

        // Fetches the account details from the external store API in the
        // background, so a slow store does not block the whole overview.
        async loadStoreUserInfo() {
            if (!this.status?.store?.loggedIn) {
                this.storeUser = null;
                return;
            }

            this.storeUser = undefined;
            try {
                const result =
                    await this.froshToolsService.getAppsStoreUserInfo();
                this.storeUser = result?.user ?? null;
            } catch {
                this.storeUser = null;
            }
        },

        async checkReachability() {
            this.isChecking = true;
            try {
                const reachability =
                    await this.froshToolsService.checkAppsReachability();
                this.status = { ...this.status, reachability };
            } catch {
                this.createNotificationError({
                    message: this.$t(
                        'frosh-tools.tabs.apps.reachability.checkError'
                    ),
                });
            } finally {
                this.isChecking = false;
            }
        },

        requestShopIdReset() {
            if (!this.canUpdate || this.isResetting) {
                return;
            }

            this.showResetModal = false;
            this.showVerifyResetModal = true;
        },

        async onResetShopIdVerified() {
            if (
                !this.showVerifyResetModal ||
                !this.canUpdate ||
                this.isResetting
            ) {
                return;
            }

            this.showVerifyResetModal = false;
            await this.resetShopId();
        },

        async resetShopId() {
            this.isResetting = true;
            try {
                const result = await this.froshToolsService.resetAppsShopId(
                    this.resetKeepUserData
                );
                this.showResetModal = false;
                this.hideShopId();
                this.createNotificationSuccess({
                    message: this.$t('frosh-tools.tabs.apps.reset.success', {
                        count: result.uninstalledApps?.length ?? 0,
                    }),
                });
                if (result.failedApps?.length) {
                    this.createNotificationWarning({
                        message: this.$t('frosh-tools.tabs.apps.reset.failed', {
                            apps: result.failedApps.join(', '),
                        }),
                    });
                }
                await this.loadStatus();
            } catch {
                this.createNotificationError({
                    message: this.$t('frosh-tools.tabs.apps.reset.error'),
                });
            } finally {
                this.isResetting = false;
            }
        },

        async copy(field, value) {
            try {
                await navigator.clipboard.writeText(value);
                this.copiedField = field;
                window.setTimeout(() => {
                    if (this.copiedField === field) {
                        this.copiedField = null;
                    }
                }, 2000);
            } catch {
                this.createNotificationError({
                    message: this.$t('frosh-tools.tabs.apps.copyError'),
                });
            }
        },

        formatDate(value) {
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleString(this.$i18n.locale, {
                dateStyle: 'medium',
                timeStyle: 'short',
            });
        },
    },
});
