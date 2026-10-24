import template from './template.twig';
import './style.scss';
import { PRIVILEGE } from '../../acl/privileges.js';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-telemetry', {
    template,
    inject: ['froshToolsService', 'acl'],
    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: true,
            isSaving: false,
            isSending: false,
            status: null,
            loadError: null,
            copied: false,
        };
    },

    computed: {
        canUpdate() {
            return this.acl.can(PRIVILEGE.TELEMETRY_UPDATE);
        },

        isEnabled() {
            return this.status?.enabled === true;
        },

        isConfigured() {
            return this.status?.posthogConfigured === true;
        },

        payloadEntries() {
            const payload = this.status?.payload ?? {};

            return Object.keys(payload)
                .sort()
                .map((key) => ({ key, value: payload[key] }));
        },

        payloadJson() {
            const payload = {};
            this.payloadEntries.forEach(({ key, value }) => {
                payload[key] = value;
            });

            return JSON.stringify(payload, null, 4);
        },
    },

    created() {
        this.loadStatus();
    },

    methods: {
        async loadStatus() {
            this.isLoading = true;
            this.loadError = null;

            try {
                this.status = await this.froshToolsService.getTelemetryStatus();
            } catch (error) {
                this.status = null;
                this.loadError = error?.response?.data?.error ?? error.message;
            } finally {
                this.isLoading = false;
            }
        },

        async onToggleEnabled(enabled) {
            this.isSaving = true;
            try {
                this.status =
                    await this.froshToolsService.setTelemetryEnabled(enabled);
                this.createNotificationSuccess({
                    message: this.$t(
                        enabled
                            ? 'frosh-tools.tabs.telemetry.optIn.enabledSuccess'
                            : 'frosh-tools.tabs.telemetry.optIn.disabledSuccess'
                    ),
                });
            } catch {
                this.createNotificationError({
                    message: this.$t(
                        'frosh-tools.tabs.telemetry.optIn.saveError'
                    ),
                });
                await this.loadStatus();
            } finally {
                this.isSaving = false;
            }
        },

        async sendNow() {
            this.isSending = true;
            try {
                const result = await this.froshToolsService.sendTelemetryNow();
                if (result?.sent === true) {
                    this.createNotificationSuccess({
                        message: this.$t(
                            'frosh-tools.tabs.telemetry.send.success'
                        ),
                    });
                    await this.loadStatus();
                } else {
                    this.createNotificationError({
                        message: this.$t(
                            `frosh-tools.tabs.telemetry.send.${this.reasonKey(result?.reason)}`
                        ),
                    });
                }
            } catch {
                this.createNotificationError({
                    message: this.$t('frosh-tools.tabs.telemetry.send.failed'),
                });
            } finally {
                this.isSending = false;
            }
        },

        async resetInstanceId() {
            this.isSaving = true;
            try {
                this.status =
                    await this.froshToolsService.resetTelemetryInstanceId();
                this.createNotificationSuccess({
                    message: this.$t(
                        'frosh-tools.tabs.telemetry.status.instanceIdResetSuccess'
                    ),
                });
            } catch {
                this.createNotificationError({
                    message: this.$t(
                        'frosh-tools.tabs.telemetry.optIn.saveError'
                    ),
                });
            } finally {
                this.isSaving = false;
            }
        },

        reasonKey(reason) {
            return (
                {
                    'not-enabled': 'notEnabled',
                    'not-configured': 'notConfigured',
                }[reason] ?? 'failed'
            );
        },

        formatValue(value) {
            return typeof value === 'boolean'
                ? value
                    ? 'true'
                    : 'false'
                : String(value);
        },

        async copyPayload() {
            try {
                await navigator.clipboard.writeText(this.payloadJson);
                this.copied = true;
                window.setTimeout(() => {
                    this.copied = false;
                }, 2000);
            } catch {
                this.createNotificationError({
                    message: this.$t(
                        'frosh-tools.tabs.telemetry.payload.copyError'
                    ),
                });
            }
        },
    },
});
