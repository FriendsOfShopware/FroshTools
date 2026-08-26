import { describe, expect, it, vi } from 'vitest';
import {
    allowConsoleMessage,
    flushPromises,
} from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { mountRegistered } from '../../../../../test/helpers';
import '../../../../mixin/sortable-table';
import './index';

const CACHE_STATS = {
    opcache: { hitRate: 97.5, hits: 1000, misses: 10, memoryUsage: 50 },
};

const DB_STATS = {
    tables: [
        { name: 'product', totalSize: 100 },
        { name: 'order', totalSize: 40 },
    ],
};

async function createWrapper({ fail = false } = {}) {
    return mountRegistered('frosh-tools-tab-statistics', {
        provide: {
            froshToolsService: {
                getCacheStatistics: fail
                    ? vi.fn().mockRejectedValue(new Error('cache fail'))
                    : vi.fn().mockResolvedValue(CACHE_STATS),
                getDatabaseStatistics: fail
                    ? vi.fn().mockRejectedValue(new Error('db fail'))
                    : vi.fn().mockResolvedValue(DB_STATS),
            },
        },
    });
}

describe('frosh-tools-tab-statistics', () => {
    it('loads cache and database statistics', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.cacheStats).toEqual(CACHE_STATS);
        expect(wrapper.vm.largestTableSize).toBe(100);
        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.vm.hitRateVariant(97)).toBe('success');
        expect(wrapper.vm.hitRateVariant(82)).toBe('warning');
        expect(wrapper.vm.fillVariant(91)).toBe('danger');
        expect(wrapper.vm.formatUptime(90000)).toBe('1d 1h 0m');
        expect(wrapper.vm.tableSizeWidth(50)).toBe(50);
    });

    it('keeps panels empty when statistics fail to load', async () => {
        allowConsoleMessage('[frosh-tools] failed to load cache statistics');
        allowConsoleMessage('[frosh-tools] failed to load database statistics');

        const wrapper = await createWrapper({ fail: true });
        await flushPromises();

        expect(wrapper.vm.cacheStats).toBeNull();
        expect(wrapper.vm.dbStats).toBeNull();
        expect(wrapper.vm.largestTableSize).toBe(0);
    });
});
