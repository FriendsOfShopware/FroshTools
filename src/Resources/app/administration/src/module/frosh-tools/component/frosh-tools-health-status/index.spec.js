import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    flushPromises,
    mountShopwareComponent,
} from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import './index';

async function createWrapper({
    canRead = true,
    health = [{ state: 'STATE_OK' }],
} = {}) {
    return mountShopwareComponent('frosh-tools-health-status', {
        global: {
            provide: {
                froshToolsService: {
                    healthStatus: vi.fn().mockResolvedValue(health),
                },
                acl: {
                    can: (privilege) =>
                        canRead && privilege === 'frosh_tools:read',
                },
                loginService: {
                    addOnLogoutListener: vi.fn(),
                },
            },
            stubs: {
                'sw-color-badge': true,
                'router-link': {
                    template:
                        '<a class="frosh-tools-health-status"><slot /></a>',
                },
            },
            directives: { tooltip: {} },
        },
    });
}

describe('frosh-tools-health-status', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('skips health polling without the tools privilege', async () => {
        const wrapper = await createWrapper({ canRead: false });
        await flushPromises();

        expect(wrapper.vm.hasPermission).toBe(false);
        expect(wrapper.vm.health).toBeNull();
        expect(wrapper.find('.frosh-tools-health-status').exists()).toBe(false);
    });

    it('maps mixed health results to the worst variant', async () => {
        vi.useFakeTimers();
        const wrapper = await createWrapper({
            health: [
                { state: 'STATE_WARNING' },
                { state: 'STATE_ERROR' },
                { state: 'STATE_OK' },
            ],
        });
        await flushPromises();

        expect(wrapper.vm.hasPermission).toBe(true);
        expect(wrapper.vm.healthVariant).toBe('error');
        expect(wrapper.vm.healthPlaceholder).toContain('May outage');
        expect(wrapper.find('sw-color-badge-stub').exists()).toBe(true);

        wrapper.unmount();
    });

    it('uses a warning variant when no check is in error', async () => {
        const wrapper = await createWrapper({
            health: [{ state: 'STATE_OK' }, { state: 'STATE_WARNING' }],
        });
        await flushPromises();

        expect(wrapper.vm.healthVariant).toBe('warning');
        expect(wrapper.vm.healthPlaceholder).toContain('Issues');
    });

    it('clears the poll interval on unmount', async () => {
        vi.useFakeTimers();
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.checkInterval).not.toBeNull();

        wrapper.unmount();

        expect(wrapper.vm.checkInterval).toBeNull();
    });
});
