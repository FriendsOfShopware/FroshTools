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
});
