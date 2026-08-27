import { describe, expect, it } from 'vitest';
import { mountShopwareComponent } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import './index';

describe('ft-icon', () => {
    it('renders the lucide svg for a known icon name', async () => {
        const wrapper = await mountShopwareComponent('ft-icon', {
            props: { name: 'refresh' },
        });

        expect(wrapper.find('.ft-icon').exists()).toBe(true);
        expect(wrapper.html()).toContain('<svg');
        expect(wrapper.element.style.width).toBe('14px');
    });

    it('accepts a custom size and stays empty for unknown names', async () => {
        const sized = await mountShopwareComponent('ft-icon', {
            props: { name: 'check', size: 22 },
        });
        expect(sized.element.style.width).toBe('22px');
        expect(sized.element.style.height).toBe('22px');

        const unknown = await mountShopwareComponent('ft-icon', {
            props: { name: 'does-not-exist' },
        });
        expect(unknown.html()).not.toContain('<svg');
    });
});
