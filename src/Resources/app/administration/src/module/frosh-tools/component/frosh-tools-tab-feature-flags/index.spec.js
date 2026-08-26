import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { mountRegistered } from '../../../../../test/helpers';
import '../../../../mixin/sortable-table';
import './index';

const FLAGS = [
    { flag: 'FEATURE_NEXT_1', description: 'Orders', active: true },
    { flag: 'V6_7_0_0', description: 'Major', active: false },
];

async function createWrapper({
    flags = FLAGS,
    searchTerm = '',
    error = null,
} = {}) {
    return mountRegistered('frosh-tools-tab-feature-flags', {
        provide: {
            froshToolsService: {
                getFeatureFlags: error
                    ? vi.fn().mockRejectedValue(error)
                    : vi.fn().mockResolvedValue(flags),
            },
            froshToolsSearch: { searchTerm },
        },
    });
}

describe('frosh-tools-tab-feature-flags', () => {
    it('loads flags and filters them by the shared search term', async () => {
        const wrapper = await createWrapper({ searchTerm: 'orders' });
        await flushPromises();

        expect(wrapper.vm.visibleFlags).toEqual([FLAGS[0]]);
    });

    it('surfaces a load error', async () => {
        const wrapper = await createWrapper({
            error: new Error('flags unavailable'),
        });
        await flushPromises();

        expect(wrapper.vm.loadError).toBe('flags unavailable');
        expect(wrapper.find('ft-hero-state-stub').exists()).toBe(true);
    });
});
