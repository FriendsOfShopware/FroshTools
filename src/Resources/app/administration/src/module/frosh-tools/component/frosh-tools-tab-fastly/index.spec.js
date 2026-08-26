import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { createAcl, mountRegistered } from '../../../../../test/helpers';
import '../../../../mixin/sortable-table';
import './index';

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
} = {}) {
    return mountRegistered('frosh-tools-tab-fastly', {
        provide: {
            froshToolsService: service,
            acl: createAcl(canUpdate, 'frosh_tools_fastly:update'),
        },
        stubs: { 'sw-code-editor': true, 'sw-text-field': true },
    });
}

describe('frosh-tools-tab-fastly', () => {
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
});
