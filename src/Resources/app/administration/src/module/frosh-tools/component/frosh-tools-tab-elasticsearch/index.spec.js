import { mount } from '@vue/test-utils';
import '../../../../mixin/sortable-table';
import '../ft-modal';
import './index';

function createService() {
    return {
        status: jest.fn().mockResolvedValue({
            info: { version: { number: '8.11.0' } },
            health: { status: 'green', number_of_nodes: 1 },
        }),
        indices: jest.fn().mockResolvedValue([]),
        deleteIndex: jest.fn().mockResolvedValue({}),
        flushAll: jest.fn().mockResolvedValue({}),
        reset: jest.fn().mockResolvedValue({}),
        reindex: jest.fn().mockResolvedValue({}),
        switchAlias: jest.fn().mockResolvedValue({}),
    };
}

async function createWrapper(service) {
    const [component, ftModal] = await Promise.all([
        Shopware.Component.build('frosh-tools-tab-elasticsearch'),
        Shopware.Component.build('ft-modal'),
    ]);

    return mount(component, {
        global: {
            provide: { froshElasticSearch: service },
            components: { 'ft-modal': ftModal },
            stubs: [
                'ft-page-head',
                'ft-panel',
                'ft-empty',
                'ft-hero-state',
                'ft-pill',
                'ft-icon',
                'ft-th-sort',
                'ft-refresh-button',
                'sw-code-editor',
                'teleport',
            ],
            mocks: {
                $t: (key) => key,
                $tc: (key) => key,
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

        // The confirm modal renders the matching snippet for the action.
        const modalText = wrapper.find('[role="dialog"]').text();
        expect(modalText).toContain(
            'frosh-tools.tabs.elasticsearch.confirm.deleteIndex.title'
        );
        expect(modalText).toContain(
            'frosh-tools.tabs.elasticsearch.confirm.deleteIndex.confirm'
        );
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

        const notifySuccess = jest.spyOn(
            wrapper.vm,
            'createNotificationSuccess'
        );

        wrapper.vm.askFlushAll();
        await wrapper.vm.runConfirmedAction();
        await flushPromises();

        expect(service.flushAll).toHaveBeenCalledTimes(1);
        expect(notifySuccess).toHaveBeenCalled();
        expect(wrapper.vm.confirmAction).toBeNull();
        // Refreshed status + indices after the action.
        expect(service.status).toHaveBeenCalledTimes(2);
        expect(service.indices).toHaveBeenCalledTimes(2);
    });

    it('keeps the modal open and notifies when the action fails', async () => {
        const service = createService();
        service.reset.mockRejectedValue(new Error('boom'));
        const wrapper = await createWrapper(service);
        await flushPromises();

        const notifyError = jest.spyOn(wrapper.vm, 'createNotificationError');

        wrapper.vm.askReset();
        await wrapper.vm.runConfirmedAction();

        expect(service.reset).toHaveBeenCalledTimes(1);
        expect(notifyError).toHaveBeenCalledWith({ message: 'boom' });
        expect(wrapper.vm.confirmAction).not.toBeNull();
        expect(wrapper.vm.isConfirmingAction).toBe(false);
    });
});
