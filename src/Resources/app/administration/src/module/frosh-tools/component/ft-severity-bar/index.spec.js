import { describe, expect, it } from 'vitest';
import { mountShopwareComponent } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import './index';

describe('ft-severity-bar', () => {
    it('uses a critical verdict and lists every non-zero count', async () => {
        const wrapper = await mountShopwareComponent('ft-severity-bar', {
            props: {
                summary: {
                    critical: 2,
                    high: 1,
                    medium: 0,
                    low: 0,
                    unknown: 0,
                    ok: 4,
                },
            },
        });

        expect(wrapper.vm.verdictVariant).toBe('danger');
        expect(wrapper.vm.actionable).toBe(3);
        expect(wrapper.find('.ft-severity-bar__verdict-text').text()).toContain(
            'frosh-tools.tabs.security.verdict.critical'
        );
        expect(wrapper.findAll('.ft-severity-bar__seg')).toHaveLength(3);
        expect(wrapper.text()).toContain('2');
        expect(wrapper.text()).toContain('4');
    });

    it('treats an empty summary as healthy', async () => {
        const wrapper = await mountShopwareComponent('ft-severity-bar', {
            props: {
                summary: {
                    critical: 0,
                    high: 0,
                    medium: 0,
                    low: 0,
                    unknown: 0,
                    ok: 0,
                },
            },
        });

        expect(wrapper.vm.verdictVariant).toBe('success');
        expect(wrapper.find('.ft-severity-bar__seg--empty').exists()).toBe(true);
    });
});
