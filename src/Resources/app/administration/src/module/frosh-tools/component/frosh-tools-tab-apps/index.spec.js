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
        shopId: 'abc123shopid',
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
            shopId: 'newshopid456',
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
                .mockResolvedValue(createStatus({ store: { loggedIn: false } })),
        });
        const wrapper = await createWrapper({ service });
        await flushPromises();

        expect(service.getAppsStoreUserInfo).not.toHaveBeenCalled();
        expect(wrapper.vm.storeUser).toBeNull();
    });

    it('masks the shop id until it is revealed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.shopIdDisplay).not.toContain('abc123shopid');

        wrapper.vm.shopIdVisible = true;
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.shopIdDisplay).toBe('abc123shopid');
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

    it('resets the shop id and reloads the status', async () => {
        const service = createService();
        const wrapper = await createWrapper({ service });
        await flushPromises();

        wrapper.vm.resetKeepUserData = true;
        await wrapper.vm.resetShopId();
        await flushPromises();

        expect(service.resetAppsShopId).toHaveBeenCalledWith(true);
        expect(wrapper.vm.showResetModal).toBe(false);
        expect(wrapper.vm.shopIdVisible).toBe(true);
        expect(service.getAppsStatus).toHaveBeenCalledTimes(2);
    });

    it('warns about apps that failed to uninstall', async () => {
        const notification = vi.fn();
        const service = createService({
            resetAppsShopId: vi.fn().mockResolvedValue({
                shopId: 'newshopid456',
                uninstalledApps: [],
                failedApps: ['BrokenApp'],
            }),
        });
        const wrapper = await createWrapper({ service });
        wrapper.vm.createNotificationWarning = notification;
        await flushPromises();

        await wrapper.vm.resetShopId();
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
