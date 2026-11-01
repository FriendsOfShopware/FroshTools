import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { mountRegistered } from '../../../../../test/helpers.js';
import './index.js';

const alarm = {
    id: 'composer-audit',
    state: 'STATE_WARNING',
    title: 'Composer-Sicherheitshinweise',
    current: '1 advisories on 1 packages',
    recommended: 'review and update affected dependencies',
    acknowledgement: '',
};

function acl(canUpdate) {
    return { can: (privilege) => canUpdate && privilege === 'frosh_tools:update' };
}

async function createWrapper(item, service, canUpdate = true) {
    return mountRegistered('frosh-tools-acknowledge-action', {
        props: { item },
        provide: {
            froshToolsService: service,
            acl: acl(canUpdate),
        },
    });
}

describe('frosh-tools-acknowledge-action', () => {
    it('hides the action without the update privilege', async () => {
        const wrapper = await createWrapper(alarm, {}, false);

        expect(wrapper.find('.ft-acknowledge__trigger').exists()).toBe(false);
    });

    it('saves a required reason and asks the list to reload', async () => {
        const service = {
            acknowledgeCheck: vi.fn().mockResolvedValue({}),
        };
        const wrapper = await createWrapper(alarm, service);

        await wrapper.find('.ft-acknowledge__trigger').trigger('click');
        const save = wrapper
            .findAll('.ft-btn')
            .find((button) => button.text() === 'Show as info');
        await save.trigger('click');
        expect(service.acknowledgeCheck).not.toHaveBeenCalled();

        await wrapper.find('textarea').setValue('Reviewed the advisory');
        await save.trigger('click');
        await flushPromises();

        expect(service.acknowledgeCheck).toHaveBeenCalledWith(
            'composer-audit',
            'Reviewed the advisory'
        );
        expect(wrapper.emitted('changed')).toHaveLength(1);
    });

    it('offers to restore an acknowledged check', async () => {
        const service = {
            revokeAcknowledgedCheck: vi.fn().mockResolvedValue({
                source: null,
            }),
        };
        const wrapper = await createWrapper(
            { ...alarm, state: 'STATE_INFO', acknowledgement: 'Reviewed' },
            service
        );

        expect(wrapper.find('.ft-acknowledge__trigger').text()).toBe(
            'Remove confirmation'
        );
        await wrapper.find('.ft-acknowledge__trigger').trigger('click');
        expect(wrapper.find('.ft-acknowledge__stored').text()).toContain(
            'Reviewed'
        );
        const confirm = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Show the alert again');
        await confirm.trigger('click');
        await flushPromises();

        expect(service.revokeAcknowledgedCheck).toHaveBeenCalledWith(
            'composer-audit'
        );
    });
});
