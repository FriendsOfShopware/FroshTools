import { mount } from '@vue/test-utils';
import '../../../../mixin/sortable-table';
import './index';

const STUBS = [
    'ft-page-head',
    'ft-panel',
    'ft-empty',
    'ft-hero-state',
    'ft-th-sort',
    'ft-pill',
    'ft-modal',
    'ft-button',
    'ft-refresh-button',
];

async function createWrapper(service) {
    const component = await Shopware.Component.build('frosh-tools-tab-index');

    return mount(component, {
        global: {
            provide: {
                froshToolsService: service,
            },
            stubs: STUBS,
            mocks: {
                $t: (key) => key,
                $tc: (key) => key,
            },
        },
    });
}

describe('frosh-tools-tab-index', () => {
    it('loads health and performance status on creation', async () => {
        const service = {
            healthStatus: jest.fn().mockResolvedValue([{ id: 'php' }]),
            performanceStatus: jest.fn().mockResolvedValue([]),
        };

        const wrapper = await createWrapper(service);
        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.vm.loadError).toBeNull();
        expect(wrapper.vm.health).toEqual([{ id: 'php' }]);
        expect(wrapper.vm.performanceStatus).toEqual([]);
    });

    it('shows an error state instead of loading forever when loading fails', async () => {
        const service = {
            healthStatus: jest
                .fn()
                .mockRejectedValue(new Error('Request failed')),
            performanceStatus: jest.fn().mockResolvedValue([]),
        };

        const wrapper = await createWrapper(service);
        const notifyError = jest.spyOn(wrapper.vm, 'createNotificationError');
        await flushPromises();

        // No infinite spinner: loading finished and an error is surfaced.
        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.vm.loadError).toBe('Request failed');
        expect(notifyError).toHaveBeenCalledWith({
            message: 'Request failed',
        });

        await wrapper.vm.$nextTick();
        expect(wrapper.find('ft-hero-state-stub').exists()).toBe(true);
    });

    it('recovers when retrying after a failure', async () => {
        const service = {
            healthStatus: jest
                .fn()
                .mockRejectedValueOnce(new Error('Request failed'))
                .mockResolvedValue([{ id: 'php' }]),
            performanceStatus: jest.fn().mockResolvedValue([]),
        };

        const wrapper = await createWrapper(service);
        await flushPromises();
        expect(wrapper.vm.loadError).toBe('Request failed');

        await wrapper.vm.refresh();
        await flushPromises();

        expect(wrapper.vm.loadError).toBeNull();
        expect(wrapper.vm.health).toEqual([{ id: 'php' }]);
        expect(wrapper.vm.isLoading).toBe(false);
    });
});
