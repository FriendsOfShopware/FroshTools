import { mountFrosh } from 'frosh-test/mount';
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

/**
 * Mounts the tab with the real ft-* component tree (including the real
 * ft-modal for the confirmation dialog) and real translations. Only the
 * elasticsearch API service (the HTTP boundary) is faked; the Shopware
 * core code editor stays stubbed.
 */
async function createWrapper(service) {
    return mountFrosh('frosh-tools-tab-elasticsearch', {
        provide: { froshElasticSearch: service },
        stubs: ['sw-code-editor'],
        attachTo: document.body,
    });
}

/**
 * Success notifications are growl-only and never persisted; errors are
 * persisted. Read both store slices to see what the user was shown.
 */
function notificationsOfVariant(variant) {
    const store = Shopware.Store.get('notification');

    return [
        ...Object.values(store.growlNotifications),
        ...Object.values(store.notifications),
    ].filter((notification) => notification.variant === variant);
}

describe('frosh-tools-tab-elasticsearch destructive actions', () => {
    beforeEach(() => {
        const store = Shopware.Store.get('notification');
        store.growlNotifications = {};
        store.notifications = {};
    });

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

        // The confirm modal renders the real snippets for the action.
        const modalText = wrapper.find('[role="dialog"]').text();
        expect(modalText).toContain('Delete index "shopware-product"?');
        expect(modalText).toContain('Delete index');
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

        wrapper.vm.askFlushAll();
        await wrapper.vm.runConfirmedAction();
        await flushPromises();

        expect(service.flushAll).toHaveBeenCalledTimes(1);
        expect(notificationsOfVariant('success')).toHaveLength(1);
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

        wrapper.vm.askReset();
        await wrapper.vm.runConfirmedAction();

        expect(service.reset).toHaveBeenCalledTimes(1);
        expect(
            notificationsOfVariant('error').map((n) => n.message)
        ).toContain('boom');
        expect(wrapper.vm.confirmAction).not.toBeNull();
        expect(wrapper.vm.isConfirmingAction).toBe(false);
    });
});
