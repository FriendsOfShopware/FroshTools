import { describe, expect, it } from 'vitest';
import { mountShopwareComponent } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import '../ft-icon';
import './index';

describe('ft-empty', () => {
    it('shows a status spinner while loading', async () => {
        const wrapper = await mountShopwareComponent('ft-empty', {
            props: { loading: true, title: 'Hidden while loading' },
        });

        expect(wrapper.attributes('role')).toBe('status');
        expect(wrapper.find('.ft-spinner').exists()).toBe(true);
        expect(wrapper.find('.ft-empty__title').exists()).toBe(false);
    });

    it('renders title, icon and supporting copy', async () => {
        const wrapper = await mountShopwareComponent('ft-empty', {
            props: {
                icon: 'search',
                title: 'No results',
                sub: 'Try another term',
            },
        });

        expect(wrapper.find('.ft-empty__title').text()).toBe('No results');
        expect(wrapper.find('.ft-empty__sub').text()).toBe('Try another term');
        expect(wrapper.find('.ft-icon').exists()).toBe(true);
    });
});
