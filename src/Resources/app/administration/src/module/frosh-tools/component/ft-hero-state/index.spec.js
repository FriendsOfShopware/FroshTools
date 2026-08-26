import { describe, expect, it } from 'vitest';
import { mountShopwareComponent } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import '../ft-icon';
import './index';

describe('ft-hero-state', () => {
    it('renders a danger callout with title, body and actions', async () => {
        const wrapper = await mountShopwareComponent('ft-hero-state', {
            props: {
                variant: 'danger',
                icon: 'alert',
                title: 'Request failed',
                body: 'Network error',
            },
            slots: { actions: '<button type="button">Retry</button>' },
            global: { stubs: { 'ft-icon': true } },
        });

        expect(wrapper.classes()).toContain('ft-hero-state--danger');
        expect(wrapper.find('.ft-hero-state__title').text()).toBe(
            'Request failed'
        );
        expect(wrapper.find('.ft-hero-state__body').text()).toBe(
            'Network error'
        );
        expect(wrapper.find('.ft-hero-state__actions').text()).toBe('Retry');
    });
});
