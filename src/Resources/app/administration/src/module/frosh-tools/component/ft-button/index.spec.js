import { describe, expect, it } from 'vitest';
import { mountShopwareComponent } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import '../ft-icon';
import './index';

describe('ft-button', () => {
    it('applies variant and icon-only classes', async () => {
        const wrapper = await mountShopwareComponent('ft-button', {
            props: { variant: 'danger', iconOnly: true, icon: 'trash' },
            slots: { default: 'Delete' },
        });

        expect(wrapper.classes()).toContain('ft-btn');
        expect(wrapper.classes()).toContain('ft-btn--danger');
        expect(wrapper.classes()).toContain('ft-btn--icon');
        expect(wrapper.text()).toContain('Delete');
        expect(wrapper.find('.ft-icon').exists()).toBe(true);
    });

    it('emits click and honors disabled', async () => {
        const enabled = await mountShopwareComponent('ft-button', {
            slots: { default: 'Save' },
        });
        await enabled.trigger('click');
        expect(enabled.emitted('click')).toHaveLength(1);

        const disabled = await mountShopwareComponent('ft-button', {
            props: { disabled: true },
            slots: { default: 'Save' },
        });
        expect(disabled.attributes('disabled')).toBeDefined();
    });
});
