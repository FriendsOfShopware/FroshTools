import { describe, expect, it } from 'vitest';
import { mountShopwareComponent } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import './index';

describe('ft-page-head', () => {
    it('renders title and optional subtitle', async () => {
        const wrapper = await mountShopwareComponent('ft-page-head', {
            props: { title: 'Cache', subtitle: 'Pools and theme compile' },
        });

        expect(wrapper.find('.ft-page-head__title').text()).toBe('Cache');
        expect(wrapper.find('.ft-page-head__sub').text()).toBe(
            'Pools and theme compile'
        );
    });

    it('renders the actions slot and hides the subtitle when empty', async () => {
        const wrapper = await mountShopwareComponent('ft-page-head', {
            props: { title: 'Queue' },
            slots: { actions: '<button type="button">Refresh</button>' },
        });

        expect(wrapper.find('.ft-page-head__sub').exists()).toBe(false);
        expect(wrapper.find('.ft-page-head__actions').text()).toBe('Refresh');
    });
});
