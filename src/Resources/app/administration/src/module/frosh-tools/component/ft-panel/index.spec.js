import { describe, expect, it } from 'vitest';
import { mountShopwareComponent } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import './index';

describe('ft-panel', () => {
    it('renders title, count and body', async () => {
        const wrapper = await mountShopwareComponent('ft-panel', {
            props: { title: 'Pools', count: 3 },
            slots: { default: '<p>Body</p>' },
        });

        expect(wrapper.find('.ft-panel__title').text()).toContain('Pools');
        expect(wrapper.find('.ft-panel__count').text()).toBe('3');
        expect(wrapper.find('.ft-panel__body').text()).toBe('Body');
        expect(wrapper.find('.ft-panel__body--flush').exists()).toBe(false);
    });

    it('uses a flush body and hides the header when it has no title or slots', async () => {
        const flush = await mountShopwareComponent('ft-panel', {
            props: { flush: true },
            slots: { default: 'Table' },
        });

        expect(flush.find('.ft-panel__head').exists()).toBe(false);
        expect(flush.find('.ft-panel__body--flush').exists()).toBe(true);
        expect(flush.text()).toContain('Table');
    });
});
