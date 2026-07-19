import { mountFrosh } from 'frosh-test/mount';
import './index';

const TASKS = [
    {
        id: 'task-1',
        name: 'log_entry.cleanup',
        runInterval: 86400,
        status: 'scheduled',
        lastExecutionTime: null,
        nextExecutionTime: null,
    },
    {
        id: 'task-2',
        name: 'shopware.invalidate_cache',
        runInterval: 300,
        status: 'scheduled',
        lastExecutionTime: null,
        nextExecutionTime: null,
    },
];

/**
 * Mounts the tab with the real ft-* component tree (panel, table headers,
 * empty states, modals) and real translations. Only the system boundaries
 * are faked: the repository (HTTP) and the shared admin search. Shopware
 * core form fields inside the edit modal stay stubbed.
 */
async function createWrapper({ searchTerm = '' } = {}) {
    const scheduledRepository = {
        search: jest.fn().mockResolvedValue(TASKS),
        save: jest.fn().mockResolvedValue({}),
    };

    return mountFrosh('frosh-tools-tab-scheduled', {
        provide: {
            repositoryFactory: { create: () => scheduledRepository },
            froshToolsService: {},
            froshToolsSearch: { searchTerm },
        },
        stubs: {
            'sw-number-field': true,
            'sw-datepicker': true,
        },
    });
}

describe('frosh-tools-tab-scheduled search', () => {
    it('shows all tasks without a search term', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.visibleItems).toHaveLength(2);
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
    });

    it('filters tasks by the shared admin search term', async () => {
        const wrapper = await createWrapper({ searchTerm: 'log_entry' });
        await flushPromises();

        expect(wrapper.vm.visibleItems).toHaveLength(1);
        expect(wrapper.vm.visibleItems[0].name).toBe('log_entry.cleanup');

        const rows = wrapper.findAll('tbody tr');
        expect(rows).toHaveLength(1);
        expect(rows[0].text()).toContain('log_entry.cleanup');
    });

    it('shows a no-results state when nothing matches', async () => {
        const wrapper = await createWrapper({ searchTerm: 'does-not-exist' });
        await flushPromises();

        expect(wrapper.vm.visibleItems).toHaveLength(0);
        expect(wrapper.findAll('tbody tr')).toHaveLength(0);

        const emptyState = wrapper.find('.ft-empty');
        expect(emptyState.exists()).toBe(true);
    });
});
