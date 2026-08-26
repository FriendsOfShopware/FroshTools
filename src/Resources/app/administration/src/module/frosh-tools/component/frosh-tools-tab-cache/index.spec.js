import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { createAcl, mountRegistered } from '../../../../../test/helpers';
import '../../../../mixin/sortable-table';
import './index';

const POOLS = [
    { name: 'http_cache', type: 'filesystem', size: 1048576, freeSpace: 0 },
    { name: 'object_cache', type: 'redis', size: 2048, freeSpace: 1024 },
];

function createService(overrides = {}) {
    return {
        getCacheInfo: vi.fn().mockResolvedValue(POOLS),
        clearCache: vi.fn().mockResolvedValue({}),
        clearOPcache: vi.fn().mockResolvedValue({}),
        ...overrides,
    };
}

async function createWrapper({
    service = createService(),
    canUpdate = true,
    searchTerm = '',
} = {}) {
    return mountRegistered('frosh-tools-tab-cache', {
        provide: {
            froshToolsService: service,
            repositoryFactory: { create: () => ({ search: vi.fn() }) },
            themeService: { assignTheme: vi.fn() },
            acl: createAcl(canUpdate, 'frosh_tools_cache:update'),
            froshToolsSearch: { searchTerm },
        },
    });
}

describe('frosh-tools-tab-cache', () => {
    it('loads cache pools and filters them by the shared search term', async () => {
        const wrapper = await createWrapper({ searchTerm: 'redis' });
        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.vm.visibleCacheFolders).toEqual([POOLS[1]]);
    });

    it('surfaces a load error and recovers on retry', async () => {
        const service = createService({
            getCacheInfo: vi
                .fn()
                .mockRejectedValueOnce(new Error('cache down'))
                .mockResolvedValue(POOLS),
        });
        const wrapper = await createWrapper({ service });
        await flushPromises();

        expect(wrapper.vm.loadError).toBe('cache down');
        expect(wrapper.find('ft-hero-state-stub').exists()).toBe(true);

        await wrapper.vm.createdComponent();
        await flushPromises();

        expect(wrapper.vm.loadError).toBeNull();
        expect(wrapper.vm.cacheFolders).toEqual(POOLS);
    });

    it('clears a pool and formats sizes in MiB', async () => {
        const service = createService();
        const wrapper = await createWrapper({ service });
        await flushPromises();

        const notifySuccess = vi.spyOn(wrapper.vm, 'createNotificationSuccess');
        await wrapper.vm.clearCache(POOLS[0]);
        await flushPromises();

        expect(service.clearCache).toHaveBeenCalledWith('http_cache');
        expect(notifySuccess).toHaveBeenCalled();
        expect(wrapper.vm.formatSize(1048576)).toMatch(/1[.,]00 MiB/);
    });

    it('hides compile and opcache actions without update privileges', async () => {
        const wrapper = await createWrapper({ canUpdate: false });
        await flushPromises();

        expect(wrapper.vm.canUpdate).toBe(false);
        expect(wrapper.text()).not.toContain('frosh-tools.compileTheme');
        expect(wrapper.text()).not.toContain('frosh-tools.clearOpCache');
    });
});
