import { describe, expect, it } from 'vitest';
import { mountShopwareComponent } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import './select';

describe('security activity select', () => {
    it('labels native selection, updates asynchronous choices and allows clearing', async () => {
        const wrapper = await mountShopwareComponent(
            'frosh-tools-security-activity-select',
            {
                props: {
                    label: 'Actor',
                    placeholder: 'All actors',
                    showClearableButton: true,
                    options: [{ value: 'alice', label: 'Alice' }],
                },
            }
        );
        expect(wrapper.find('label').text()).toContain('Actor');
        await wrapper.find('select').setValue('alice');
        expect(wrapper.emitted('update:value')?.[0]).toEqual(['alice']);
        await wrapper.setProps({
            value: 'alice',
            options: [
                { value: 'alice', label: 'Alice' },
                { value: 'bob', label: 'Bob' },
                { value: 'hidden', label: 'Hidden', hidden: true },
            ],
        });
        expect(
            wrapper.findAll('option').map((option) => option.text())
        ).toEqual(['All actors', 'Alice', 'Bob']);
        await wrapper.find('select').setValue('');
        expect(wrapper.emitted('update:value')?.[1]).toEqual([null]);
        wrapper.unmount();
    });

    it('emits remote search without submitting the filters or changing selection', async () => {
        const wrapper = await mountShopwareComponent(
            'frosh-tools-security-activity-select',
            {
                props: { label: 'Actor', searchable: true, value: 'alice' },
            }
        );
        await wrapper.find('input[type="search"]').setValue('bob');
        expect(wrapper.emitted('search')?.[0]).toEqual(['bob']);
        expect(wrapper.emitted('update:value')).toBeUndefined();
        wrapper.unmount();
    });
});
