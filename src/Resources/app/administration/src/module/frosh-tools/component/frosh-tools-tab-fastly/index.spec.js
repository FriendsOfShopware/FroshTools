import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { createAcl, mountRegistered } from '../../../../../test/helpers.js';
import '../../../../mixin/sortable-table.js';
import './index.js';

function createService(overrides = {}) {
    return {
        getFastlyStatistics: vi.fn().mockResolvedValue({ requests: 12 }),
        getFastlySnippets: vi.fn().mockResolvedValue([{ name: 'recv' }]),
        fastlyPurgeAll: vi.fn().mockResolvedValue({}),
        fastlyPurge: vi.fn().mockResolvedValue({}),
        ...overrides,
    };
}

async function createWrapper({
    service = createService(),
    canUpdate = true,
    section = 'traffic',
} = {}) {
    return mountRegistered('frosh-tools-tab-fastly', {
        global: { mocks: { $route: { query: { section } } } },
        provide: {
            froshToolsService: service,
            acl: createAcl(canUpdate, 'frosh_tools_fastly:update'),
        },
        stubs: {
            'sw-code-editor': true,
            'sw-text-field': true,
            'sw-single-select': true,
        },
    });
}

describe('frosh-tools-tab-fastly', () => {
    it('reloads statistics when the labeled timeframe selection changes', async () => {
        const service = createService();
        const wrapper = await createWrapper({ service });
        await flushPromises();
        const select = wrapper.find('select');
        expect(select.attributes('aria-label')).toBeTruthy();
        await select.setValue('7d');
        await flushPromises();
        expect(service.getFastlyStatistics).toHaveBeenLastCalledWith('7d');
        wrapper.unmount();
    });

    it('loads stats and snippets for the default timeframe', async () => {
        const service = createService();
        const wrapper = await createWrapper({ service });
        await flushPromises();

        expect(service.getFastlyStatistics).toHaveBeenCalledWith('2h');
        expect(wrapper.vm.stats).toEqual({ requests: 12 });
        expect(wrapper.vm.snippets).toEqual([{ name: 'recv' }]);
        expect(wrapper.vm.timeframeOptions).toHaveLength(6);
    });

    it('purges all and a single path', async () => {
        const service = createService();
        const wrapper = await createWrapper({ service });
        await flushPromises();

        const notifySuccess = vi.spyOn(wrapper.vm, 'createNotificationSuccess');

        await wrapper.vm.onPurgeAll();
        expect(service.fastlyPurgeAll).toHaveBeenCalledTimes(1);

        wrapper.vm.purgePath = '/media/logo.png';
        await wrapper.vm.onPurge();
        expect(service.fastlyPurge).toHaveBeenCalledWith('/media/logo.png');
        expect(wrapper.vm.purgePath).toBe('');
        expect(notifySuccess).toHaveBeenCalledTimes(2);
    });

    it('does not purge an empty path and hides actions without privileges', async () => {
        const service = createService();
        const wrapper = await createWrapper({ service, canUpdate: false });
        await flushPromises();

        await wrapper.vm.onPurge();
        expect(service.fastlyPurge).not.toHaveBeenCalled();
        expect(wrapper.vm.canUpdate).toBe(false);
    });
    it('shows purge controls only on the permitted purge page', async () => {
        const traffic = await createWrapper();
        await flushPromises();
        expect(traffic.find('sw-text-field-stub').exists()).toBe(false);
        traffic.unmount();
        const purge = await createWrapper({ section: 'purge' });
        await flushPromises();
        expect(purge.find('sw-text-field-stub').exists()).toBe(true);
        purge.unmount();
        const restricted = await createWrapper({
            section: 'purge',
            canUpdate: false,
        });
        await flushPromises();
        expect(restricted.find('sw-text-field-stub').exists()).toBe(false);
        expect(restricted.vm.activeSection).toBe('traffic');
        restricted.unmount();
    });
    it('shows a snippets failure and can recover without refetching traffic', async () => {
        const service = createService({
            getFastlySnippets: vi
                .fn()
                .mockRejectedValueOnce(new Error('Fastly unavailable'))
                .mockResolvedValue([]),
        });
        const wrapper = await createWrapper({ section: 'snippets', service });
        await flushPromises();
        expect(wrapper.find('ft-hero-state-stub').attributes('body')).toBe(
            'Fastly unavailable'
        );
        await wrapper.find('ft-refresh-button-stub').trigger('click');
        await flushPromises();
        expect(wrapper.find('ft-hero-state-stub').exists()).toBe(false);
        expect(wrapper.find('ft-empty-stub').attributes('title')).toBe(
            'No VCL snippets reported'
        );
        expect(service.getFastlyStatistics).toHaveBeenCalledTimes(1);
        wrapper.unmount();
    });
});
