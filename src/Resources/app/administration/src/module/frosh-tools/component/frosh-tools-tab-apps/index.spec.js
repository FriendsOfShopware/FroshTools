import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { createAcl, mountRegistered } from '../../../../../test/helpers';
import './index';

function createStatus(overrides = {}) {
    return {
        appUrl: 'https://shop.example.com',
        reachability: {
            status: 'pass',
            checkedAt: '2026-09-02T06:00:00+00:00',
            info: null,
            detailed: true,
        },
        store: {
            loggedIn: true,
        },
        hasShopId: true,
        apps: [
            {
                name: 'FroshTestApp',
                label: 'Frosh Test App',
                version: '1.0.0',
                active: true,
            },
        ],
        ...overrides,
    };
}

function createService(overrides = {}) {
    return {
        getAppsShopId: vi.fn().mockResolvedValue({ shopId: 'abc123shopid' }),
        getAppsStatus: vi.fn().mockResolvedValue(createStatus()),
        getAppsStoreUserInfo: vi.fn().mockResolvedValue({
            user: {
                name: 'Jane Doe',
                email: 'jane@example.com',
                avatarUrl: null,
            },
        }),
        checkAppsReachability: vi.fn().mockResolvedValue({
            status: 'hard_fail',
            checkedAt: '2026-09-02T07:00:00+00:00',
            info: 'HTTPS is required.',
            detailed: true,
        }),
        resetAppsShopId: vi.fn().mockResolvedValue({
            hasShopId: true,
            uninstalledApps: ['FroshTestApp'],
            failedApps: [],
        }),
        ...overrides,
    };
}

async function createWrapper({
    service = createService(),
    canUpdate = true,
} = {}) {
    return mountRegistered('frosh-tools-tab-apps', {
        stubs: {
            'sw-verify-user-modal': {
                name: 'sw-verify-user-modal',
                emits: ['verified', 'close'],
                template: '<div class="verify-user-modal" />',
            },
            'sw-switch-field': {
                name: 'sw-switch-field',
                props: ['value', 'label'],
                emits: ['update:value'],
                template:
                    '<button @click="$emit(\'update:value\', !value)">{{ label }}</button>',
            },
        },
        provide: {
            froshToolsService: service,
            acl: createAcl(canUpdate, 'frosh_tools_apps:update'),
        },
    });
}

describe('frosh-tools-tab-apps', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('loads the status overview', async () => {
        const service = createService();
        const wrapper = await createWrapper({ service });
        await flushPromises();

        expect(wrapper.vm.status.appUrl).toBe('https://shop.example.com');
        expect(wrapper.vm.reachabilityVariant).toBe('success');
        expect(wrapper.vm.apps).toHaveLength(1);
        expect(wrapper.text()).toContain('FroshTestApp');

        expect(service.getAppsStoreUserInfo).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.storeUser.email).toBe('jane@example.com');
    });

    it('skips the store user request when logged out', async () => {
        const service = createService({
            getAppsStatus: vi
                .fn()
                .mockResolvedValue(
                    createStatus({ store: { loggedIn: false } })
                ),
        });
        const wrapper = await createWrapper({ service });
        await flushPromises();

        expect(service.getAppsStoreUserInfo).not.toHaveBeenCalled();
        expect(wrapper.vm.storeUser).toBeNull();
    });

    it('reveals the shop id only after password verification', async () => {
        const service = createService();
        const wrapper = await createWrapper({ service });
        await flushPromises();

        expect(wrapper.vm.shopId).toBeNull();
        expect(wrapper.vm.shopIdDisplay).not.toContain('abc123shopid');
        const showButton = wrapper
            .findAll('button')
            .find((button) => button.attributes('icon') === 'eye');
        await showButton.trigger('click');
        expect(service.getAppsShopId).not.toHaveBeenCalled();
        expect(wrapper.vm.shopIdVisible).toBe(false);

        wrapper
            .findComponent({ name: 'sw-verify-user-modal' })
            .vm.$emit('verified');
        await flushPromises();
        expect(service.getAppsShopId).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.shopIdDisplay).toBe('abc123shopid');
        expect(wrapper.vm.showVerifyShopIdModal).toBe(false);

        const hideButton = wrapper
            .findAll('button')
            .find((button) => button.attributes('icon') === 'eye-off');
        await hideButton.trigger('click');
        expect(wrapper.vm.shopId).toBeNull();
        wrapper.vm.onShowShopId();
        await wrapper.vm.$nextTick();
        expect(
            wrapper.findComponent({ name: 'sw-verify-user-modal' }).exists()
        ).toBe(true);
        expect(service.getAppsShopId).toHaveBeenCalledTimes(1);
    });

    it('keeps the shop id hidden when verification is cancelled or fails', async () => {
        const service = createService();
        const wrapper = await createWrapper({ service });
        await flushPromises();
        wrapper.vm.onShowShopId();
        await wrapper.vm.$nextTick();

        wrapper
            .findComponent({ name: 'sw-verify-user-modal' })
            .vm.$emit('close');
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.shopIdVisible).toBe(false);
        expect(service.getAppsShopId).not.toHaveBeenCalled();
    });

    it('keeps the shop id hidden if the verified request is rejected', async () => {
        const service = createService({
            getAppsShopId: vi
                .fn()
                .mockRejectedValue(new Error('Invalid scope')),
        });
        const wrapper = await createWrapper({ service });
        await flushPromises();
        const notification = vi.spyOn(wrapper.vm, 'createNotificationError');
        await wrapper.vm.onShopIdVerified();
        expect(wrapper.vm.shopIdVisible).toBe(false);
        expect(wrapper.vm.shopId).toBeNull();
        expect(notification).toHaveBeenCalled();
    });

    it('does not reveal an id from a pending request after refreshing', async () => {
        let resolveShopId;
        const service = createService({
            getAppsShopId: vi.fn().mockReturnValue(
                new Promise((resolve) => {
                    resolveShopId = resolve;
                })
            ),
        });
        const wrapper = await createWrapper({ service });
        await flushPromises();

        const revealing = wrapper.vm.onShopIdVerified();
        await wrapper.vm.loadStatus();
        resolveShopId({ shopId: 'stale-shop-id' });
        await revealing;
        expect(wrapper.vm.shopId).toBeNull();
        expect(wrapper.vm.shopIdVisible).toBe(false);
    });

    it('copies the revealed shop id', async () => {
        const writeText = vi.fn().mockResolvedValue();
        vi.stubGlobal('navigator', { clipboard: { writeText } });

        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.copy('shopId', 'abc123shopid');

        expect(writeText).toHaveBeenCalledWith('abc123shopid');
        expect(wrapper.vm.copiedField).toBe('shopId');
    });

    it('runs the reachability check and updates the state', async () => {
        const service = createService();
        const wrapper = await createWrapper({ service });
        await flushPromises();

        await wrapper.vm.checkReachability();
        await flushPromises();

        expect(service.checkAppsReachability).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.reachability.status).toBe('hard_fail');
        expect(wrapper.vm.reachabilityVariant).toBe('danger');
    });

    it('asks for the password after confirming the reset and resets only once verified', async () => {
        const service = createService();
        const wrapper = await createWrapper({ service });
        await flushPromises();
        wrapper.vm.showResetModal = true;
        await wrapper.vm.$nextTick();

        const confirmButton = wrapper.find(
            '[role="dialog"] button[icon="trash"]'
        );
        await confirmButton.trigger('click');
        expect(wrapper.vm.showResetModal).toBe(false);
        expect(service.resetAppsShopId).not.toHaveBeenCalled();
        const verifyModal = wrapper.findComponent({
            name: 'sw-verify-user-modal',
        });
        expect(verifyModal.exists()).toBe(true);
        verifyModal.vm.$emit('verified');
        verifyModal.vm.$emit('verified');
        verifyModal.vm.$emit('close');
        await flushPromises();
        expect(service.resetAppsShopId).toHaveBeenCalledExactlyOnceWith(false);
        expect(wrapper.vm.showVerifyResetModal).toBe(false);
    });

    it('does not reset when password verification is cancelled or fails', async () => {
        const service = createService();
        const wrapper = await createWrapper({ service });
        await flushPromises();
        wrapper.vm.requestShopIdReset();
        await wrapper.vm.$nextTick();
        wrapper
            .findComponent({ name: 'sw-verify-user-modal' })
            .vm.$emit('close');
        await wrapper.vm.$nextTick();
        await wrapper.vm.onResetShopIdVerified();
        expect(wrapper.vm.showVerifyResetModal).toBe(false);
        expect(service.resetAppsShopId).not.toHaveBeenCalled();
    });

    it('does not offer or perform reset for a viewer', async () => {
        const service = createService();
        const wrapper = await createWrapper({ service, canUpdate: false });
        await flushPromises();
        wrapper.vm.requestShopIdReset();
        await wrapper.vm.onResetShopIdVerified();
        expect(wrapper.vm.showVerifyResetModal).toBe(false);
        expect(service.resetAppsShopId).not.toHaveBeenCalled();
    });

    it('reports a rejected verified reset without clearing the current id', async () => {
        const service = createService({
            resetAppsShopId: vi
                .fn()
                .mockRejectedValue(new Error('Invalid scope')),
        });
        const wrapper = await createWrapper({ service });
        await flushPromises();
        await wrapper.vm.onShopIdVerified();
        const notification = vi.spyOn(wrapper.vm, 'createNotificationError');
        wrapper.vm.requestShopIdReset();
        await wrapper.vm.onResetShopIdVerified();
        expect(notification).toHaveBeenCalled();
        expect(wrapper.vm.isResetting).toBe(false);
        expect(wrapper.vm.shopId).toBe('abc123shopid');
        expect(service.getAppsStatus).toHaveBeenCalledTimes(1);
    });

    it('uses the switch value when resetting the shop id', async () => {
        const service = createService();
        const wrapper = await createWrapper({ service });
        await flushPromises();
        wrapper.vm.showResetModal = true;
        await wrapper.vm.$nextTick();

        const field = wrapper.findComponent({ name: 'sw-switch-field' });
        expect(field.props('value')).toBe(false);
        await field.trigger('click');
        expect(wrapper.vm.resetKeepUserData).toBe(true);
        wrapper.vm.requestShopIdReset();
        await wrapper.vm.onResetShopIdVerified();
        expect(service.resetAppsShopId).toHaveBeenCalledWith(true);
    });

    it('resets the shop id and reloads the status', async () => {
        const service = createService();
        const wrapper = await createWrapper({ service });
        await flushPromises();

        await wrapper.vm.onShopIdVerified();
        expect(wrapper.vm.shopIdVisible).toBe(true);
        wrapper.vm.resetKeepUserData = true;
        wrapper.vm.requestShopIdReset();
        await wrapper.vm.onResetShopIdVerified();
        await flushPromises();

        expect(service.resetAppsShopId).toHaveBeenCalledWith(true);
        expect(wrapper.vm.showResetModal).toBe(false);
        expect(wrapper.vm.shopIdVisible).toBe(false);
        expect(wrapper.vm.shopId).toBeNull();
        expect(service.getAppsStatus).toHaveBeenCalledTimes(2);
    });

    it('warns about apps that failed to uninstall', async () => {
        const notification = vi.fn();
        const service = createService({
            resetAppsShopId: vi.fn().mockResolvedValue({
                hasShopId: true,
                uninstalledApps: [],
                failedApps: ['BrokenApp'],
            }),
        });
        const wrapper = await createWrapper({ service });
        wrapper.vm.createNotificationWarning = notification;
        await flushPromises();

        wrapper.vm.requestShopIdReset();
        await wrapper.vm.onResetShopIdVerified();
        await flushPromises();

        expect(notification).toHaveBeenCalledTimes(1);
    });

    it('hides the danger zone without update privileges', async () => {
        const wrapper = await createWrapper({ canUpdate: false });
        await flushPromises();

        expect(wrapper.find('.frosh-tab-apps__danger').exists()).toBe(false);
    });

    it('shows the danger zone with update privileges', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.frosh-tab-apps__danger').exists()).toBe(true);
    });

    it('shows an error state when loading fails', async () => {
        const service = createService({
            getAppsStatus: vi.fn().mockRejectedValue(new Error('boom')),
        });
        const wrapper = await createWrapper({ service });
        await flushPromises();

        expect(wrapper.vm.loadError).toBe('boom');
    });
});
