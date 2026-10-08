import { describe, expect, it } from 'vitest';
import {
    loadShopwareComponent,
    shallowMountShopwareComponent,
} from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import './select';

describe('security activity select', () => {
    it('allows searching and refreshes asynchronously loaded choices while open', async () => {
        await loadShopwareComponent('sw-single-select');
        const wrapper = await shallowMountShopwareComponent(
            'frosh-tools-security-activity-select',
            {
                global: {
                    mocks: {
                        isCompatEnabled: () => false,
                    },
                    stubs: {
                        'sw-select-base': {
                            template:
                                '<div><slot name="sw-select-selection" /></div>',
                        },
                        'sw-select-result-list': true,
                        'sw-select-result': true,
                        'sw-highlight-text': true,
                    },
                },
                props: {
                    value: null,
                    options: [{ value: 'alice', label: 'Alice' }],
                },
            }
        );
        expect(wrapper.vm.disableSearchFunction).toBe(false);
        await wrapper.setData({ isExpanded: true, searchTerm: 'bob' });
        wrapper.vm.search();
        expect(wrapper.vm.visibleResults).toEqual([]);
        await wrapper.setProps({ options: [{ value: 'bob', label: 'Bob' }] });
        expect(wrapper.vm.visibleResults).toEqual([
            { value: 'bob', label: 'Bob' },
        ]);
        await wrapper.setProps({
            value: 'bob',
            options: [
                { value: 'alice', label: 'Alice' },
                { value: 'bob', label: 'Bob' },
                { value: 'hidden', label: 'Hidden', hidden: true },
            ],
        });
        wrapper.vm.onSelectExpanded();
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.searchTerm).toBe('Bob');
        expect(wrapper.vm.visibleResults.map((option) => option.value)).toEqual(
            ['alice', 'bob']
        );
        await wrapper.setData({ searchTerm: 'ali' });
        wrapper.vm.search();
        expect(wrapper.vm.visibleResults.map((option) => option.value)).toEqual(
            ['alice']
        );
        wrapper.unmount();
    });
});
