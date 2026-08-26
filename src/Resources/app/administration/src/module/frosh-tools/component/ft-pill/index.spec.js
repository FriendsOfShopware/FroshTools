import { describe, expect, it } from 'vitest';
import { mountShopwareComponent } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import './index';

describe('ft-pill', () => {
    it('renders variant and bare modifiers', async () => {
        const wrapper = await mountShopwareComponent('ft-pill', {
            props: { variant: 'warning', bare: true },
            slots: { default: 'Scheduled' },
        });

        expect(wrapper.classes()).toEqual([
            'ft-pill',
            'ft-pill--warning',
            'ft-pill--bare',
        ]);
        expect(wrapper.text()).toBe('Scheduled');
    });
});
