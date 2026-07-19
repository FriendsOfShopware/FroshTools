import { filterCommands, getCommandDefinitions } from './commands';

describe('ft-command-palette/commands', () => {
    it('exposes navigation and action commands', () => {
        const commands = getCommandDefinitions();

        expect(commands.some((command) => command.id === 'nav.index')).toBe(
            true
        );
        expect(
            commands.some((command) => command.id === 'action.cache.clear-all')
        ).toBe(true);
        expect(
            commands.some((command) => command.id === 'action.queue.reset')
        ).toBe(true);
    });

    it('hides conditional navigation when features are unavailable', () => {
        const elasticsearch = getCommandDefinitions().find(
            (command) => command.id === 'nav.elasticsearch'
        );

        expect(elasticsearch.available({ elasticsearchAvailable: false })).toBe(
            false
        );
        expect(elasticsearch.available({ elasticsearchAvailable: true })).toBe(
            true
        );
    });

    it('filters commands by label and keywords', () => {
        const commands = [
            {
                id: 'nav.cache',
                label: 'Cache',
                description: 'Manage cache pools',
                groupLabel: 'Go to',
                keywords: ['opcache', 'theme'],
            },
            {
                id: 'nav.queue',
                label: 'Queue',
                description: 'Pending messages',
                groupLabel: 'Go to',
                keywords: ['messenger'],
            },
            {
                id: 'action.cache.clear-all',
                label: 'Clear all cache pools',
                description: 'Flush every pool',
                groupLabel: 'Cache',
                keywords: ['clear', 'flush'],
            },
        ];

        const cacheHits = filterCommands(commands, 'cache');
        expect(cacheHits.map((command) => command.id)).toEqual([
            'nav.cache',
            'action.cache.clear-all',
        ]);

        const messengerHits = filterCommands(commands, 'messenger');
        expect(messengerHits.map((command) => command.id)).toEqual([
            'nav.queue',
        ]);

        expect(filterCommands(commands, 'does-not-exist')).toEqual([]);
    });

    it('ranks exact and prefix label matches higher', () => {
        const commands = [
            {
                id: 'a',
                label: 'Cache manager tools',
                description: '',
                groupLabel: '',
                keywords: [],
            },
            {
                id: 'b',
                label: 'Cache',
                description: '',
                groupLabel: '',
                keywords: [],
            },
            {
                id: 'c',
                label: 'Clear all cache pools',
                description: '',
                groupLabel: '',
                keywords: ['cache'],
            },
        ];

        const ranked = filterCommands(commands, 'cache');
        expect(ranked[0].id).toBe('b');
    });

    it('clears matching cache pools and reports missing ones', async () => {
        const clearHttp = getCommandDefinitions().find(
            (command) => command.id === 'action.cache.clear-http'
        );

        const froshToolsService = {
            getCacheInfo: jest
                .fn()
                .mockResolvedValue([{ name: 'http_cache' }, { name: 'object' }]),
            clearCache: jest.fn().mockResolvedValue({}),
        };
        const notifySuccess = jest.fn();
        const notifyError = jest.fn();

        await clearHttp.run({
            froshToolsService,
            notifySuccess,
            notifyError,
            t: (key, params) => `${key}:${JSON.stringify(params || {})}`,
        });

        expect(froshToolsService.clearCache).toHaveBeenCalledWith('http_cache');
        expect(notifySuccess).toHaveBeenCalled();
        expect(notifyError).not.toHaveBeenCalled();
    });

    it('resets the queue via the service', async () => {
        const reset = getCommandDefinitions().find(
            (command) => command.id === 'action.queue.reset'
        );

        const froshToolsService = {
            resetQueue: jest.fn().mockResolvedValue({}),
        };
        const notifySuccess = jest.fn();

        await reset.run({
            froshToolsService,
            notifySuccess,
            notifyError: jest.fn(),
            t: (key) => key,
        });

        expect(froshToolsService.resetQueue).toHaveBeenCalled();
        expect(notifySuccess).toHaveBeenCalledWith({
            message: 'frosh-tools.tabs.queue.reset.success',
        });
    });
});
