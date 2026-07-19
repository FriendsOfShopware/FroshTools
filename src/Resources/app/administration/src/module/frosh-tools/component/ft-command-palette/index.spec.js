import { mountFrosh } from 'frosh-test/mount';
import './index';

/**
 * Mounts the palette with the real ft-* components and real translations.
 * Only the system boundaries are faked: the API services (HTTP) and the
 * router (navigation).
 */
async function createWrapper({
    props = {},
    service = {},
    routerPush = jest.fn().mockResolvedValue(),
} = {}) {
    return mountFrosh('ft-command-palette', {
        props: {
            elasticsearchAvailable: true,
            logsAvailable: true,
            fastlyAvailable: true,
            ...props,
        },
        provide: {
            froshToolsService: {
                getCacheInfo: jest.fn().mockResolvedValue([]),
                clearCache: jest.fn().mockResolvedValue({}),
                clearOPcache: jest.fn().mockResolvedValue({}),
                resetQueue: jest.fn().mockResolvedValue({}),
                scheduledTasksRegister: jest.fn().mockResolvedValue({}),
                ...service,
            },
            themeService: {
                assignTheme: jest.fn().mockResolvedValue({}),
            },
            repositoryFactory: {
                create: jest.fn(),
            },
        },
        mocks: {
            $router: {
                push: routerPush,
            },
        },
        attachTo: document.body,
    });
}

describe('ft-command-palette', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        window.localStorage.clear();
    });

    it('renders the search field and navigation commands', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.ft-command-palette__input').exists()).toBe(true);
        expect(wrapper.text()).toContain('System-Status');
        expect(wrapper.text()).toContain('Cache');
    });

    it('filters the list when the query changes', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const input = wrapper.find('.ft-command-palette__input');
        await input.setValue('queue');
        await flushPromises();

        expect(wrapper.text()).toContain('Queue');
        expect(wrapper.text()).not.toContain('Feature Flags');
    });

    it('navigates when a navigation command is activated', async () => {
        const routerPush = jest.fn().mockResolvedValue();
        const wrapper = await createWrapper({ routerPush });
        await flushPromises();

        const indexCommand = wrapper.vm.visibleCommands.find(
            (command) => command.id === 'nav.index'
        );
        expect(indexCommand).toBeTruthy();

        await wrapper.vm.runCommand(indexCommand);
        await flushPromises();

        expect(routerPush).toHaveBeenCalledWith({
            name: 'frosh.tools.index.index',
        });
        expect(wrapper.emitted('close')).toBeTruthy();
    });

    it('asks for confirmation before destructive actions', async () => {
        const resetQueue = jest.fn().mockResolvedValue({});
        const wrapper = await createWrapper({
            service: { resetQueue },
        });
        await flushPromises();

        const resetCommand = wrapper.vm.visibleCommands.find(
            (command) => command.id === 'action.queue.reset'
        );
        expect(resetCommand).toBeTruthy();

        await wrapper.vm.runCommand(resetCommand);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.pendingConfirm).toBeTruthy();
        expect(resetQueue).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Confirm action');

        await wrapper.vm.confirmAndRun();
        await flushPromises();

        expect(resetQueue).toHaveBeenCalled();
        expect(wrapper.emitted('close')).toBeTruthy();
    });

    it('closes on Escape', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        document.dispatchEvent(
            new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })
        );
        await flushPromises();

        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('hides unavailable feature navigation', async () => {
        const wrapper = await createWrapper({
            props: {
                elasticsearchAvailable: false,
                logsAvailable: false,
                fastlyAvailable: false,
            },
        });
        await flushPromises();

        const ids = wrapper.vm.visibleCommands.map((command) => command.id);
        expect(ids).not.toContain('nav.elasticsearch');
        expect(ids).not.toContain('nav.logs');
        expect(ids).not.toContain('nav.fastly');
    });
});
