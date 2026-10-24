import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { mountRegistered } from '../../../../../test/helpers.js';
import '../../../../mixin/sortable-table.js';
import '../../../../mixin/settings-result.js';
import './index.js';

async function createWrapper(service) {
    return mountRegistered('frosh-tools-tab-index', {
        provide: {
            froshToolsService: service,
        },
    });
}

describe('frosh-tools-tab-index', () => {
    it('loads health and performance status on creation', async () => {
        const service = {
            healthStatus: vi.fn().mockResolvedValue([{ id: 'php' }]),
            performanceStatus: vi.fn().mockResolvedValue([]),
        };

        const wrapper = await createWrapper(service);
        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.vm.loadError).toBeNull();
        expect(wrapper.vm.health).toEqual([{ id: 'php' }]);
        expect(wrapper.vm.performanceStatus).toEqual([]);
    });

    it('shows an error state instead of loading forever when loading fails', async () => {
        const service = {
            healthStatus: vi
                .fn()
                .mockRejectedValue(new Error('Request failed')),
            performanceStatus: vi.fn().mockResolvedValue([]),
        };

        const wrapper = await createWrapper(service);
        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.vm.loadError).toBe('Request failed');

        await wrapper.vm.$nextTick();
        expect(wrapper.find('ft-hero-state-stub').exists()).toBe(true);
    });

    it('recovers when retrying after a failure', async () => {
        const service = {
            healthStatus: vi
                .fn()
                .mockRejectedValueOnce(new Error('Request failed'))
                .mockResolvedValue([{ id: 'php' }]),
            performanceStatus: vi.fn().mockResolvedValue([]),
        };

        const wrapper = await createWrapper(service);
        await flushPromises();
        expect(wrapper.vm.loadError).toBe('Request failed');

        await wrapper.vm.refresh();
        await flushPromises();

        expect(wrapper.vm.loadError).toBeNull();
        expect(wrapper.vm.health).toEqual([{ id: 'php' }]);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('maps health states to pill variants and recommendation info', async () => {
        const wrapper = await createWrapper({
            healthStatus: vi.fn().mockResolvedValue([]),
            performanceStatus: vi.fn().mockResolvedValue([]),
        });
        await flushPromises();

        expect(wrapper.vm.pillVariant('STATE_ERROR')).toBe('danger');
        expect(wrapper.vm.pillVariant('STATE_WARNING')).toBe('warning');
        expect(wrapper.vm.hasInfo({ id: 'queue' })).toBe(true);
        expect(wrapper.vm.hasInfo({ id: 'unknown-check' })).toBe(false);
    });
    it('summarizes health and performance findings together', async () => {
        const wrapper = await createWrapper({
            healthStatus: vi
                .fn()
                .mockResolvedValue([
                    { state: 'STATE_OK' },
                    { state: 'STATE_WARNING' },
                ]),
            performanceStatus: vi
                .fn()
                .mockResolvedValue([{ state: 'STATE_ERROR' }]),
        });
        await flushPromises();

        const summary = wrapper.findAll('ft-hero-state-stub')[0];
        expect(summary.attributes('variant')).toBe('danger');
        expect(summary.attributes('body')).toBe(
            'Checks: 3 · Errors: 1 · Warnings: 1'
        );
    });

    it('does not show a summary while loading or when no checks were returned', async () => {
        let resolveHealth;
        const wrapper = await createWrapper({
            healthStatus: () =>
                new Promise((resolve) => {
                    resolveHealth = resolve;
                }),
            performanceStatus: vi.fn().mockResolvedValue([]),
        });
        expect(wrapper.find('.frosh-tab-index__summary').exists()).toBe(false);
        resolveHealth([]);
        await flushPromises();
        expect(wrapper.find('.frosh-tab-index__summary').exists()).toBe(false);
    });
});
