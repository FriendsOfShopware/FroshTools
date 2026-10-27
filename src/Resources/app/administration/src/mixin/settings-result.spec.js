import { describe, expect, it } from 'vitest';
import { mount } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import './settings-result.js';

function createWrapper() {
    return mount(
        {
            template: '<div />',
            mixins: [Shopware.Mixin.getByName('frosh-settings-result')],
        },
        { attachTo: false }
    );
}

describe('frosh-settings-result mixin', () => {
    it('maps result states to pill variants', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.pillVariant('STATE_ERROR')).toBe('danger');
        expect(wrapper.vm.pillVariant('STATE_WARNING')).toBe('warning');
        expect(wrapper.vm.pillVariant('STATE_INFO')).toBe('info');
        expect(wrapper.vm.pillVariant('STATE_OK')).toBe('success');
    });

    it('maps result states to translated labels', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.stateLabel('STATE_ERROR')).toBe('Error');
        expect(wrapper.vm.stateLabel('STATE_WARNING')).toBe('Warning');
        expect(wrapper.vm.stateLabel('STATE_INFO')).toBe('Info');
        expect(wrapper.vm.stateLabel('STATE_OK')).toBe('Good');
    });

    it('prefers the translated result title and falls back to the backend label', () => {
        const wrapper = createWrapper();
        const prefix = 'frosh-tools.tabs.data-integrity.recommendations';

        expect(
            wrapper.vm.resultTitle(prefix, {
                id: 'rule-invalid',
                snippet: 'Backend label',
            })
        ).toBe('Rules with invalid conditions');
        expect(
            wrapper.vm.resultTitle(prefix, {
                id: 'third-party-check',
                snippet: 'Backend label',
            })
        ).toBe('Backend label');
    });

    it('finds snippets that only exist in the fallback locale', () => {
        const wrapper = mount(
            {
                template: '<div />',
                mixins: [Shopware.Mixin.getByName('frosh-settings-result')],
            },
            {
                attachTo: false,
                global: {
                    mocks: {
                        $te: (key, locale) => locale === 'en-GB',
                        $t: (key) => `translated:${key}`,
                    },
                },
            }
        );

        expect(
            wrapper.vm.resultRecommendation('prefix', { id: 'check' })
        ).toEqual({
            description: 'translated:prefix.check.description',
            solution: 'translated:prefix.check.solution',
        });
        expect(wrapper.vm.resultTitle('prefix', { id: 'check' })).toBe(
            'translated:prefix.check.title'
        );
    });

    it('returns no recommendation without a description', () => {
        const wrapper = createWrapper();

        expect(
            wrapper.vm.resultRecommendation(
                'frosh-tools.tabs.data-integrity.recommendations',
                { id: 'unknown-check' }
            )
        ).toBeNull();
    });
});
