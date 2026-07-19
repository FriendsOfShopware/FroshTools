import { mountFrosh } from 'frosh-test/mount';
import './index';

const PHP_HEALTH = {
    id: 'php',
    snippet: 'PHP version',
    state: 'STATE_OK',
    current: '8.3.0',
    recommended: '8.2.0',
};

/**
 * Mounts the tab with the real ft-* component tree and real translations.
 * Only the API service (the HTTP boundary) is faked.
 */
async function createWrapper(service) {
    return mountFrosh('frosh-tools-tab-index', {
        provide: {
            froshToolsService: service,
        },
    });
}

function errorNotifications() {
    return Object.values(Shopware.Store.get('notification').notifications)
        .filter((notification) => notification.variant === 'error');
}

describe('frosh-tools-tab-index', () => {
    beforeEach(() => {
        Shopware.Store.get('notification').notifications = {};
    });

    it('loads health and performance status on creation', async () => {
        const service = {
            healthStatus: jest.fn().mockResolvedValue([PHP_HEALTH]),
            performanceStatus: jest.fn().mockResolvedValue([]),
        };

        const wrapper = await createWrapper(service);
        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.vm.loadError).toBeNull();
        expect(wrapper.vm.health).toEqual([PHP_HEALTH]);
        expect(wrapper.vm.performanceStatus).toEqual([]);

        // The real table renders the loaded health row.
        expect(wrapper.find('tbody tr').exists()).toBe(true);
        expect(wrapper.find('.ft-table__name').text()).toContain(
            'PHP version'
        );
    });

    it('shows an error state instead of loading forever when loading fails', async () => {
        const service = {
            healthStatus: jest
                .fn()
                .mockRejectedValue(new Error('Request failed')),
            performanceStatus: jest.fn().mockResolvedValue([]),
        };

        const wrapper = await createWrapper(service);
        await flushPromises();

        // No infinite spinner: loading finished and an error is surfaced.
        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.vm.loadError).toBe('Request failed');

        // The failure created a real error notification.
        expect(errorNotifications().map((n) => n.message)).toContain(
            'Request failed'
        );

        await wrapper.vm.$nextTick();
        const heroState = wrapper.find('.ft-hero-state--danger');
        expect(heroState.exists()).toBe(true);
        expect(heroState.text()).toContain('Request failed');
    });

    it('recovers when retrying after a failure', async () => {
        const service = {
            healthStatus: jest
                .fn()
                .mockRejectedValueOnce(new Error('Request failed'))
                .mockResolvedValue([PHP_HEALTH]),
            performanceStatus: jest.fn().mockResolvedValue([]),
        };

        const wrapper = await createWrapper(service);
        await flushPromises();
        expect(wrapper.vm.loadError).toBe('Request failed');

        await wrapper.vm.refresh();
        await flushPromises();

        expect(wrapper.vm.loadError).toBeNull();
        expect(wrapper.vm.health).toEqual([PHP_HEALTH]);
        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.find('.ft-hero-state--danger').exists()).toBe(false);
    });
});
