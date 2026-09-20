import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    flushPromises,
    mountShopwareComponent,
} from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import './index';

async function createWrapper({
    canRead = true,
    health = [{ state: 'STATE_OK' }],
    slot = '',
} = {}) {
    return mountShopwareComponent('frosh-tools-health-status', {
        slots: slot ? { default: slot } : {},
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
                'mt-badge': {
                    props: ['variant', 'size', 'statusIndicator'],
                    template:
                        '<span class="mt-badge" :class="`mt-badge--${variant}`"><slot /></span>',
                },
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
        const wrapper = await createWrapper({
            canRead: false,
            slot: '<span class="fallback-title">Administration</span>',
        });
        await flushPromises();

        expect(wrapper.vm.hasPermission).toBe(false);
        expect(wrapper.vm.health).toBeNull();
        expect(wrapper.find('.frosh-tools-health-status').exists()).toBe(false);
        expect(wrapper.find('.fallback-title').exists()).toBe(true);
    });

    it('hides the healthy default state and keeps the fallback title', async () => {
        const wrapper = await createWrapper({
            health: [{ state: 'STATE_OK' }],
            slot: '<span class="fallback-title">Administration</span>',
        });
        await flushPromises();

        expect(wrapper.vm.isException).toBe(false);
        expect(wrapper.find('.mt-badge').exists()).toBe(false);
        expect(wrapper.find('.fallback-title').text()).toBe('Administration');
    });

    it('maps mixed health results to a critical badge', async () => {
        vi.useFakeTimers();
        const wrapper = await createWrapper({
            health: [
                { state: 'STATE_WARNING' },
                { state: 'STATE_ERROR' },
                { state: 'STATE_OK' },
            ],
            slot: '<span class="fallback-title">Administration</span>',
        });
        await flushPromises();

        expect(wrapper.vm.hasPermission).toBe(true);
        expect(wrapper.vm.isException).toBe(true);
        expect(wrapper.vm.meteorVariant).toBe('critical');
        expect(wrapper.vm.badgeLabel).toContain('Outage');
        expect(wrapper.find('.mt-badge').exists()).toBe(true);
        expect(wrapper.find('.fallback-title').exists()).toBe(false);

        wrapper.unmount();
    });

    it('uses an attention badge when no check is in error', async () => {
        const wrapper = await createWrapper({
            health: [{ state: 'STATE_OK' }, { state: 'STATE_WARNING' }],
        });
        await flushPromises();

        expect(wrapper.vm.meteorVariant).toBe('attention');
        expect(wrapper.vm.badgeLabel).toContain('Issues');
        expect(wrapper.find('.mt-badge').exists()).toBe(true);
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
