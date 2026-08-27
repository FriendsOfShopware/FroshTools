import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { createAcl, mountRegistered } from '../../../../../test/helpers';
import './index';

function createService(overrides = {}) {
    return {
        getShopmonStatus: vi.fn().mockResolvedValue({ configured: false }),
        setupShopmon: vi.fn().mockResolvedValue({
            configured: true,
            shopId: 'shop-1',
            token: 'secret',
        }),
        removeShopmon: vi.fn().mockResolvedValue({}),
        ...overrides,
    };
}

async function createWrapper({
    service = createService(),
    canUpdate = true,
} = {}) {
    return mountRegistered('frosh-tools-tab-shopmon', {
        provide: {
            froshToolsService: service,
            acl: createAcl(canUpdate, 'frosh_tools_shopmon:update'),
        },
    });
}

describe('frosh-tools-tab-shopmon', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('shows the setup hero when Shopmon is not configured', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.isConfigured).toBe(false);
        expect(wrapper.find('.frosh-tab-shopmon__hero').exists()).toBe(true);
    });

    it('sets up the integration', async () => {
        const service = createService();
        const wrapper = await createWrapper({ service });
        await flushPromises();

        await wrapper.vm.setup();
        await flushPromises();

        expect(service.setupShopmon).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.isConfigured).toBe(true);
    });

    it('copies a credential and removes the integration', async () => {
        const writeText = vi.fn().mockResolvedValue();
        vi.stubGlobal('navigator', { clipboard: { writeText } });

        const service = createService({
            getShopmonStatus: vi.fn().mockResolvedValue({
                configured: true,
                shopId: 'shop-1',
                token: 'secret',
            }),
        });
        const wrapper = await createWrapper({ service });
        await flushPromises();

        await wrapper.vm.copy('token', 'secret');
        expect(writeText).toHaveBeenCalledWith('secret');
        expect(wrapper.vm.copiedField).toBe('token');

        await wrapper.vm.removeIntegration();
        expect(service.removeShopmon).toHaveBeenCalledTimes(1);
    });

    it('hides the setup button without update privileges', async () => {
        const wrapper = await createWrapper({ canUpdate: false });
        await flushPromises();

        expect(wrapper.find('.frosh-tab-shopmon__cta-primary').exists()).toBe(
            false
        );
    });
});
