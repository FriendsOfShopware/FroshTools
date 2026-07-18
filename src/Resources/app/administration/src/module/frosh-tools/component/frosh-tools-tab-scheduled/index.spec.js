import { mount } from '@vue/test-utils';
import '../../../../mixin/sortable-table';
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

const STUBS = {
    // The panel must render its default slot — the table lives inside it.
    'ft-panel': { template: '<section><slot /></section>' },
    'ft-page-head': true,
    'ft-empty': true,
    'ft-hero-state': true,
    'ft-th-sort': { template: '<th><slot /></th>' },
    'ft-pill': true,
    'ft-icon': true,
    'ft-modal': true,
    'ft-button': true,
    'ft-refresh-button': true,
    'sw-number-field': true,
    'sw-datepicker': true,
};

async function createWrapper({ searchTerm = '' } = {}) {
    const scheduledRepository = {
        search: jest.fn().mockResolvedValue(TASKS),
        save: jest.fn().mockResolvedValue({}),
    };

    const component = await Shopware.Component.build(
        'frosh-tools-tab-scheduled'
    );

    return mount(component, {
        global: {
            provide: {
                repositoryFactory: { create: () => scheduledRepository },
                froshToolsService: {},
                froshToolsSearch: { searchTerm },
            },
            stubs: STUBS,
            mocks: {
                $t: (key) => key,
                $tc: (key) => key,
            },
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
        expect(wrapper.find('ft-empty-stub').exists()).toBe(true);
    });
});
