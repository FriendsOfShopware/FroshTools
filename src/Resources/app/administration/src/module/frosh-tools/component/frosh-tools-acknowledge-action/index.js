import template from './template.twig';
import './style.scss';
import { PRIVILEGE } from '../../acl/privileges.js';

const { Component, Mixin } = Shopware;

const ALARM_SEVERITIES = ['critical', 'high', 'medium', 'low'];

Component.register('frosh-tools-acknowledge-action', {
    template,
    inject: {
        froshToolsService: { from: 'froshToolsService' },
        acl: { from: 'acl', default: null },
    },
    mixins: [Mixin.getByName('notification')],
    props: {
        item: {
            type: Object,
            required: true,
        },
    },
    emits: ['changed'],
    data() {
        return {
            open: false,
            reason: '',
            saving: false,
        };
    },
    computed: {
        canUpdate() {
            return this.acl?.can(PRIVILEGE.UPDATE) === true;
        },

        acknowledged() {
            return Boolean(this.item?.acknowledgement);
        },

        isAlarm() {
            const item = this.item || {};
            if (
                item.state === 'STATE_ERROR' ||
                item.state === 'STATE_WARNING'
            ) {
                return true;
            }

            return ALARM_SEVERITIES.includes(item.severity);
        },

        visible() {
            return this.canUpdate && (this.isAlarm || this.acknowledged);
        },
    },
    methods: {
        showDialog() {
            this.reason = '';
            this.open = true;
        },

        close() {
            if (this.saving) {
                return;
            }
            this.open = false;
        },

        async confirm() {
            if (this.saving || !this.item?.id) {
                return;
            }

            if (!this.acknowledged && !this.reason.trim()) {
                return;
            }

            this.saving = true;
            try {
                if (this.acknowledged) {
                    const result =
                        await this.froshToolsService.revokeAcknowledgedCheck(
                            this.item.id
                        );
                    if (result?.source === 'config') {
                        this.createNotificationInfo({
                            message: this.$t(
                                'frosh-tools.acknowledge.stillConfigured'
                            ),
                        });
                    } else {
                        this.createNotificationSuccess({
                            message: this.$t('frosh-tools.acknowledge.revoked'),
                        });
                    }
                } else {
                    const reason = this.reason.trim();
                    await this.froshToolsService.acknowledgeCheck(
                        this.item.id,
                        reason
                    );
                    this.createNotificationSuccess({
                        message: this.$t('frosh-tools.acknowledge.saved'),
                    });
                }
                this.open = false;
                this.$emit('changed');
            } catch (error) {
                this.createNotificationError({
                    message:
                        error?.response?.data?.error ||
                        error?.message ||
                        this.$t('frosh-tools.acknowledge.failed'),
                });
            } finally {
                this.saving = false;
            }
        },
    },
});
