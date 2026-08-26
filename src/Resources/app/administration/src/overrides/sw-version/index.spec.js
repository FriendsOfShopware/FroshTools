import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    flushPromises,
    loadShopwareComponent,
    mountShopwareComponent,
} from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';

async function createWrapper({
    canRead = true,
    health = [{ state: 'STATE_OK' }],
} = {}) {
    await loadShopwareComponent('sw-version');
    await import('./index');

    return mountShopwareComponent('sw-version', {
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
                    template: '<a class="sw-version__status"><slot /></a>',
                },
            },
            directives: { tooltip: {} },
        },
    });
}

describe('sw-version override', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('skips health polling without the tools privilege', async () => {
        const wrapper = await createWrapper({ canRead: false });
        await flushPromises();

        expect(wrapper.vm.hasPermission).toBe(false);
        expect(wrapper.vm.health).toBeNull();
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

        wrapper.unmount();
    });
});
