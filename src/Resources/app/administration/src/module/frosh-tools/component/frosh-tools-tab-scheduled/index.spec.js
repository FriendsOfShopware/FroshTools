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

const SCHEDULES = [
    {
        name: 'example',
        stateful: true,
        checkpoint: '2026-08-23T10:00:00.000+00:00',
        error: null,
        messages: [
            {
                id: 'aaaa1111',
                scheduleName: 'example',
                label: 'app:cleanup:orders',
                messageClass:
                    'Symfony\\Component\\Console\\Messenger\\RunCommandMessage',
                trigger: '35 3 * * *',
                triggerType: 'cron',
                transports: ['low_priority'],
                nextRunDate: '2026-08-24T03:35:00.000+00:00',
                terminated: false,
            },
            {
                id: 'bbbb2222',
                scheduleName: 'example',
                label: 'app:orders-export',
                messageClass:
                    'Symfony\\Component\\Console\\Messenger\\RunCommandMessage',
                trigger: '*/2 7-19 * * 1-5',
                triggerType: 'cron',
                transports: ['async'],
                nextRunDate: null,
                terminated: true,
            },
        ],
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

async function createWrapper({
    searchTerm = '',
    canUpdate = true,
    schedules = [],
    froshToolsService = null,
} = {}) {
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
                froshToolsService: froshToolsService ?? {
                    getSymfonySchedules: jest.fn().mockResolvedValue(schedules),
                    runSymfonySchedulerTask: jest.fn().mockResolvedValue({}),
                },
                acl: {
                    can: (privilege) =>
                        canUpdate ||
                        privilege !== 'frosh_tools_scheduled_task:update',
                },
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

    it('hides task actions when the user cannot update', async () => {
        const wrapper = await createWrapper({ canUpdate: false });
        await flushPromises();

        expect(wrapper.vm.canUpdate).toBe(false);
        expect(wrapper.find('.frosh-tab-scheduled__menu-wrap').exists()).toBe(
            false
        );
    });

    it('shows a no-results state when nothing matches', async () => {
        const wrapper = await createWrapper({ searchTerm: 'does-not-exist' });
        await flushPromises();

        expect(wrapper.vm.visibleItems).toHaveLength(0);
        expect(wrapper.findAll('tbody tr')).toHaveLength(0);
        expect(wrapper.find('ft-empty-stub').exists()).toBe(true);
    });
});

describe('frosh-tools-tab-scheduled symfony scheduler', () => {
    it('renders no scheduler panel when no schedule is registered', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.visibleSchedules).toHaveLength(0);
        expect(wrapper.findAll('tbody tr')).toHaveLength(TASKS.length);
    });

    it('renders one row per recurring message', async () => {
        const wrapper = await createWrapper({ schedules: SCHEDULES });
        await flushPromises();

        expect(wrapper.vm.visibleSchedules).toHaveLength(1);

        const rows = wrapper.findAll('tbody tr');
        expect(rows).toHaveLength(TASKS.length + 2);
        expect(rows[TASKS.length].text()).toContain('app:cleanup:orders');
        expect(rows[TASKS.length].text()).toContain('35 3 * * *');
    });

    it('filters recurring messages by the shared admin search term', async () => {
        const wrapper = await createWrapper({
            schedules: SCHEDULES,
            searchTerm: 'orders-export',
        });
        await flushPromises();

        const messages = wrapper.vm.filterScheduleMessages(SCHEDULES[0]);
        expect(messages).toHaveLength(1);
        expect(messages[0].label).toBe('app:orders-export');
    });

    it('hides a schedule whose messages are all filtered out', async () => {
        const wrapper = await createWrapper({
            schedules: SCHEDULES,
            searchTerm: 'does-not-exist',
        });
        await flushPromises();

        expect(wrapper.vm.visibleSchedules).toHaveLength(0);
    });

    it('keeps a schedule visible to show its collection error', async () => {
        const wrapper = await createWrapper({
            schedules: [{ name: 'broken', messages: [], error: 'boom' }],
            searchTerm: 'does-not-exist',
        });
        await flushPromises();

        expect(wrapper.vm.visibleSchedules).toHaveLength(1);
        expect(wrapper.vm.visibleSchedules[0].error).toBe('boom');
    });

    it('keeps the shopware task table when the scheduler lookup fails', async () => {
        const wrapper = await createWrapper({
            froshToolsService: {
                getSymfonySchedules: jest
                    .fn()
                    .mockRejectedValue(new Error('nope')),
            },
        });
        await flushPromises();

        expect(wrapper.vm.schedules).toBeNull();
        expect(wrapper.vm.visibleSchedules).toHaveLength(0);
        expect(wrapper.vm.visibleItems).toHaveLength(TASKS.length);
    });

    it('hides scheduler run actions when the user cannot update', async () => {
        const wrapper = await createWrapper({
            schedules: SCHEDULES,
            canUpdate: false,
        });
        await flushPromises();

        expect(wrapper.findAll('.ft-table__actions')).toHaveLength(0);
    });

    it('dispatches a recurring message through the api service', async () => {
        const runSymfonySchedulerTask = jest.fn().mockResolvedValue({});
        const wrapper = await createWrapper({
            froshToolsService: {
                getSymfonySchedules: jest.fn().mockResolvedValue(SCHEDULES),
                runSymfonySchedulerTask,
            },
        });
        await flushPromises();

        await wrapper.vm.runSymfonyTask(SCHEDULES[0].messages[0]);

        expect(runSymfonySchedulerTask).toHaveBeenCalledWith(
            'example',
            'aaaa1111'
        );
    });
});
