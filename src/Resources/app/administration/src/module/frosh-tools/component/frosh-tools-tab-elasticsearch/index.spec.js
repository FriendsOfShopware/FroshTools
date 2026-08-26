import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    flushPromises,
    mountShopwareComponent,
} from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { createAcl, FT_STUBS } from '../../../../../test/helpers';
import '../../../../mixin/sortable-table';
import '../ft-modal';
import './index';

function createService() {
    return {
        status: vi.fn().mockResolvedValue({
            info: { version: { number: '8.11.0' } },
            health: { status: 'green', number_of_nodes: 1 },
        }),
        indices: vi.fn().mockResolvedValue([]),
        deleteIndex: vi.fn().mockResolvedValue({}),
        flushAll: vi.fn().mockResolvedValue({}),
        reset: vi.fn().mockResolvedValue({}),
        reindex: vi.fn().mockResolvedValue({}),
        switchAlias: vi.fn().mockResolvedValue({}),
    };
}

async function createWrapper(service, { canUpdate = true } = {}) {
    return mountShopwareComponent('frosh-tools-tab-elasticsearch', {
        global: {
            provide: {
                froshElasticSearch: service,
                acl: createAcl(canUpdate, 'frosh_tools_elasticsearch:update'),
            },
            stubs: {
                ...FT_STUBS,
                'sw-code-editor': true,
            },
            directives: {
                tooltip: {},
            },
        },
        attachTo: document.body,
    });
}

describe('frosh-tools-tab-elasticsearch destructive actions', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('hides destructive index actions when the user cannot update', async () => {
        const service = createService();
        service.indices.mockResolvedValue([
            { name: 'shopware-product', aliases: [], indexSize: 1, docs: 1 },
        ]);
        const wrapper = await createWrapper(service, { canUpdate: false });
        await flushPromises();

        expect(wrapper.vm.canUpdate).toBe(false);
        expect(wrapper.find('.ft-table__actions').exists()).toBe(false);
    });

    it('asks for confirmation before deleting an index', async () => {
        const service = createService();
        const wrapper = await createWrapper(service);
        await flushPromises();

        wrapper.vm.askDeleteIndex('shopware-product');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.confirmAction).toEqual({
            key: 'deleteIndex',
            indexName: 'shopware-product',
        });
        expect(service.deleteIndex).not.toHaveBeenCalled();
        expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
    });

    it('does not run the action when the confirmation is cancelled', async () => {
        const service = createService();
        const wrapper = await createWrapper(service);
        await flushPromises();

        wrapper.vm.askFlushAll();
        wrapper.vm.cancelConfirmAction();

        expect(wrapper.vm.confirmAction).toBeNull();
        expect(service.flushAll).not.toHaveBeenCalled();
    });

    it('runs the confirmed action, notifies and refreshes', async () => {
        const service = createService();
        const wrapper = await createWrapper(service);
        await flushPromises();

        const notifySuccess = vi.spyOn(wrapper.vm, 'createNotificationSuccess');

        wrapper.vm.askFlushAll();
        await wrapper.vm.runConfirmedAction();
        await flushPromises();

        expect(service.flushAll).toHaveBeenCalledTimes(1);
        expect(notifySuccess).toHaveBeenCalled();
        expect(wrapper.vm.confirmAction).toBeNull();
        expect(service.status).toHaveBeenCalledTimes(2);
        expect(service.indices).toHaveBeenCalledTimes(2);
    });

    it('keeps the modal open and notifies when the action fails', async () => {
        const service = createService();
        service.reset.mockRejectedValue(new Error('boom'));
        const wrapper = await createWrapper(service);
        await flushPromises();

        const notifyError = vi.spyOn(wrapper.vm, 'createNotificationError');

        wrapper.vm.askReset();
        await wrapper.vm.runConfirmedAction();

        expect(service.reset).toHaveBeenCalledTimes(1);
        expect(notifyError).toHaveBeenCalledWith({ message: 'boom' });
        expect(wrapper.vm.confirmAction).not.toBeNull();
        expect(wrapper.vm.isConfirmingAction).toBe(false);
    });

    it('labels OpenSearch and maps cluster health variants', async () => {
        const service = createService();
        service.status.mockResolvedValue({
            info: { version: { number: '2.11.0', distribution: 'opensearch' } },
            health: { status: 'yellow', number_of_nodes: 3 },
        });
        const wrapper = await createWrapper(service);
        await flushPromises();

        expect(wrapper.vm.engineName).toBe('OpenSearch');
        expect(wrapper.vm.engineVersion).toBe('2.11.0');
        expect(wrapper.vm.healthVariant).toBe('warning');
        expect(wrapper.vm.nodeCount).toBe(3);
    });
});
