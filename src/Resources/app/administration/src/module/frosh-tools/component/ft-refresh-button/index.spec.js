import { describe, expect, it } from 'vitest';
import { mountShopwareComponent } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import '../ft-icon';
import './index';

describe('ft-refresh-button', () => {
    it('emits click and shows the default label', async () => {
        const wrapper = await mountShopwareComponent('ft-refresh-button', {
            global: { stubs: { 'ft-icon': true } },
        });

        expect(wrapper.text()).toMatch(/Refresh|frosh-tools\.refresh/);
        await wrapper.trigger('click');
        expect(wrapper.emitted('click')).toHaveLength(1);
        expect(wrapper.find('ft-icon-stub').exists()).toBe(true);
    });

    it('disables the button and shows a spinner while loading', async () => {
        const wrapper = await mountShopwareComponent('ft-refresh-button', {
            props: { loading: true, label: 'Reloading' },
            global: { stubs: { 'ft-icon': true } },
        });

        expect(wrapper.attributes('disabled')).toBeDefined();
        expect(wrapper.find('.ft-spinner').exists()).toBe(true);
        expect(wrapper.text()).toContain('Reloading');
    });
});
