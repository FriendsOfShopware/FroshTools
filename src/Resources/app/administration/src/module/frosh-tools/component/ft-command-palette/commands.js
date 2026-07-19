/**
 * Static command definitions for the FroshTools command palette.
 *
 * Each command is either navigation (route) or an executable action.
 * Labels/descriptions are snippet keys resolved by the palette component.
 */

/**
 * @typedef {Object} CommandContext
 * @property {(location: { name: string }) => Promise<unknown>|unknown} routerPush
 * @property {Record<string, Function>} froshToolsService
 * @property {{ assignTheme: Function }} [themeService]
 * @property {{ create: Function }} [repositoryFactory]
 * @property {(payload: { message: string }) => void} notifySuccess
 * @property {(payload: { message: string }) => void} notifyError
 * @property {(key: string, params?: Record<string, unknown>) => string} t
 * @property {boolean} elasticsearchAvailable
 * @property {boolean} logsAvailable
 * @property {boolean} fastlyAvailable
 */

/**
 * @typedef {Object} CommandDefinition
 * @property {string} id
 * @property {'navigate'|'action'} type
 * @property {string} group // snippet key suffix under frosh-tools.commandPalette.groups
 * @property {string} icon
 * @property {string} labelKey
 * @property {string} [descriptionKey]
 * @property {string[]} [keywords]
 * @property {boolean} [confirm]
 * @property {string} [confirmLabelKey]
 * @property {(ctx: CommandContext) => boolean} [available]
 * @property {string} [route]
 * @property {(ctx: CommandContext) => Promise<void>|void} [run]
 */

/**
 * @returns {CommandDefinition[]}
 */
export function getCommandDefinitions() {
    return [
        // ── Navigation ──────────────────────────────────────────────
        {
            id: 'nav.index',
            type: 'navigate',
            group: 'navigate',
            icon: 'bolt',
            labelKey: 'frosh-tools.tabs.index.title',
            descriptionKey: 'frosh-tools.tabs.index.subtitle',
            keywords: ['status', 'health', 'system', 'overview', 'home'],
            route: 'frosh.tools.index.index',
        },
        {
            id: 'nav.security',
            type: 'navigate',
            group: 'navigate',
            icon: 'alert',
            labelKey: 'frosh-tools.tabs.security.title',
            descriptionKey: 'frosh-tools.tabs.security.subtitle',
            keywords: ['security', 'sbom', 'audit', 'eol', 'vulnerabilities'],
            route: 'frosh.tools.index.security',
        },
        {
            id: 'nav.shopmon',
            type: 'navigate',
            group: 'navigate',
            icon: 'send',
            labelKey: 'frosh-tools.tabs.shopmon.title',
            descriptionKey:
                'frosh-tools.commandPalette.descriptions.shopmon',
            keywords: ['shopmon', 'monitoring', 'integration'],
            route: 'frosh.tools.index.shopmon',
        },
        {
            id: 'nav.cache',
            type: 'navigate',
            group: 'navigate',
            icon: 'refresh',
            labelKey: 'frosh-tools.tabs.cache.title',
            descriptionKey: 'frosh-tools.tabs.cache.subtitle',
            keywords: ['cache', 'opcache', 'theme'],
            route: 'frosh.tools.index.cache',
        },
        {
            id: 'nav.statistics',
            type: 'navigate',
            group: 'navigate',
            icon: 'chart',
            labelKey: 'frosh-tools.tabs.statistics.title',
            descriptionKey: 'frosh-tools.tabs.statistics.subtitle',
            keywords: ['statistics', 'redis', 'fpm', 'database', 'opcache'],
            route: 'frosh.tools.index.statistics',
        },
        {
            id: 'nav.elasticsearch',
            type: 'navigate',
            group: 'navigate',
            icon: 'search',
            labelKey: 'frosh-tools.tabs.elasticsearch.title',
            descriptionKey: 'frosh-tools.tabs.elasticsearch.subtitle',
            keywords: ['elasticsearch', 'opensearch', 'index', 'search'],
            available: (ctx) => ctx.elasticsearchAvailable,
            route: 'frosh.tools.index.elasticsearch',
        },
        {
            id: 'nav.queue',
            type: 'navigate',
            group: 'navigate',
            icon: 'flow',
            labelKey: 'frosh-tools.tabs.queue.title',
            descriptionKey: 'frosh-tools.tabs.queue.subtitle',
            keywords: ['queue', 'messenger', 'messages', 'worker'],
            route: 'frosh.tools.index.queue',
        },
        {
            id: 'nav.scheduled',
            type: 'navigate',
            group: 'navigate',
            icon: 'play',
            labelKey: 'frosh-tools.tabs.scheduledTaskOverview.title',
            descriptionKey: 'frosh-tools.tabs.scheduledTaskOverview.subtitle',
            keywords: ['scheduled', 'tasks', 'cron', 'schedule'],
            route: 'frosh.tools.index.scheduled',
        },
        {
            id: 'nav.statemachines',
            type: 'navigate',
            group: 'navigate',
            icon: 'flow',
            labelKey: 'frosh-tools.tabs.state-machines.title',
            descriptionKey: 'frosh-tools.tabs.state-machines.subtitle',
            keywords: ['state', 'machine', 'order', 'transaction', 'delivery'],
            route: 'frosh.tools.index.statemachines',
        },
        {
            id: 'nav.logs',
            type: 'navigate',
            group: 'navigate',
            icon: 'file',
            labelKey: 'frosh-tools.tabs.logs.title',
            descriptionKey: 'frosh-tools.tabs.logs.subtitle',
            keywords: ['logs', 'log viewer', 'error', 'var/log'],
            available: (ctx) => ctx.logsAvailable,
            route: 'frosh.tools.index.logs',
        },
        {
            id: 'nav.featureflags',
            type: 'navigate',
            group: 'navigate',
            icon: 'cog',
            labelKey: 'frosh-tools.tabs.feature-flags.title',
            descriptionKey: 'frosh-tools.tabs.feature-flags.subtitle',
            keywords: ['feature', 'flags', 'toggle'],
            route: 'frosh.tools.index.featureflags',
        },
        {
            id: 'nav.fastly',
            type: 'navigate',
            group: 'navigate',
            icon: 'bolt',
            labelKey: 'frosh-tools.tabs.fastly.title',
            descriptionKey: 'frosh-tools.commandPalette.descriptions.fastly',
            keywords: ['fastly', 'cdn', 'purge'],
            available: (ctx) => ctx.fastlyAvailable,
            route: 'frosh.tools.index.fastly',
        },

        // ── Cache actions ───────────────────────────────────────────
        {
            id: 'action.cache.clear-all',
            type: 'action',
            group: 'cache',
            icon: 'trash',
            labelKey: 'frosh-tools.commandPalette.actions.clearAllCaches',
            descriptionKey:
                'frosh-tools.commandPalette.actions.clearAllCachesDescription',
            keywords: ['clear', 'cache', 'all', 'flush'],
            confirm: true,
            confirmLabelKey:
                'frosh-tools.commandPalette.actions.clearAllCachesConfirm',
            async run(ctx) {
                const pools = await ctx.froshToolsService.getCacheInfo();
                const list = Array.isArray(pools) ? pools : [];

                for (const pool of list) {
                    if (pool?.name) {
                        await ctx.froshToolsService.clearCache(pool.name);
                    }
                }

                ctx.notifySuccess({
                    message: ctx.t(
                        'frosh-tools.commandPalette.actions.clearAllCachesSuccess',
                        { count: list.length }
                    ),
                });
            },
        },
        {
            id: 'action.cache.clear-http',
            type: 'action',
            group: 'cache',
            icon: 'trash',
            labelKey: 'frosh-tools.commandPalette.actions.clearHttpCache',
            descriptionKey:
                'frosh-tools.commandPalette.actions.clearHttpCacheDescription',
            keywords: ['clear', 'http', 'cache', 'http_cache'],
            async run(ctx) {
                await clearCacheIfPresent(ctx, [
                    'http',
                    'http_cache',
                    'shopware.http_cache',
                ]);
            },
        },
        {
            id: 'action.cache.clear-object',
            type: 'action',
            group: 'cache',
            icon: 'trash',
            labelKey: 'frosh-tools.commandPalette.actions.clearObjectCache',
            descriptionKey:
                'frosh-tools.commandPalette.actions.clearObjectCacheDescription',
            keywords: ['clear', 'object', 'cache', 'app'],
            async run(ctx) {
                await clearCacheIfPresent(ctx, [
                    'object',
                    'app',
                    'cache.object',
                    'shopware.cache',
                ]);
            },
        },
        {
            id: 'action.cache.clear-opcache',
            type: 'action',
            group: 'cache',
            icon: 'refresh',
            labelKey: 'frosh-tools.clearOpCache',
            descriptionKey:
                'frosh-tools.commandPalette.actions.clearOpcacheDescription',
            keywords: ['opcache', 'php', 'clear'],
            async run(ctx) {
                await ctx.froshToolsService.clearOPcache();
                ctx.notifySuccess({
                    message: ctx.t('frosh-tools.clearedOpcache'),
                });
            },
        },
        {
            id: 'action.cache.compile-theme',
            type: 'action',
            group: 'cache',
            icon: 'paint',
            labelKey: 'frosh-tools.compileTheme',
            descriptionKey:
                'frosh-tools.commandPalette.actions.compileThemeDescription',
            keywords: ['theme', 'compile', 'storefront', 'scss'],
            available: (ctx) =>
                Boolean(ctx.themeService && ctx.repositoryFactory),
            async run(ctx) {
                const Criteria = Shopware.Data.Criteria;
                const criteria = new Criteria();
                criteria.addAssociation('themes');

                const salesChannelRepository =
                    ctx.repositoryFactory.create('sales_channel');
                const salesChannels = await salesChannelRepository.search(
                    criteria,
                    Shopware.Context.api
                );

                let compiled = 0;

                for (const salesChannel of salesChannels) {
                    const theme = salesChannel.extensions?.themes?.first?.();
                    if (!theme) {
                        continue;
                    }

                    await ctx.themeService.assignTheme(
                        theme.id,
                        salesChannel.id
                    );
                    compiled += 1;
                    ctx.notifySuccess({
                        message: `${salesChannel.translated?.name ?? salesChannel.name}: ${ctx.t('frosh-tools.themeCompiled')}`,
                    });
                }

                if (compiled === 0) {
                    ctx.notifyError({
                        message: ctx.t(
                            'frosh-tools.commandPalette.actions.compileThemeNone'
                        ),
                    });
                }
            },
        },

        // ── Queue actions ───────────────────────────────────────────
        {
            id: 'action.queue.reset',
            type: 'action',
            group: 'queue',
            icon: 'trash',
            labelKey: 'frosh-tools.resetQueue',
            descriptionKey:
                'frosh-tools.commandPalette.actions.resetQueueDescription',
            keywords: ['queue', 'reset', 'clear', 'purge', 'messenger'],
            confirm: true,
            confirmLabelKey:
                'frosh-tools.commandPalette.actions.resetQueueConfirm',
            async run(ctx) {
                await ctx.froshToolsService.resetQueue();
                ctx.notifySuccess({
                    message: ctx.t('frosh-tools.tabs.queue.reset.success'),
                });
            },
        },

        // ── Task actions ────────────────────────────────────────────
        {
            id: 'action.tasks.register',
            type: 'action',
            group: 'tasks',
            icon: 'refresh',
            labelKey: 'frosh-tools.commandPalette.actions.registerTasks',
            descriptionKey:
                'frosh-tools.commandPalette.actions.registerTasksDescription',
            keywords: ['scheduled', 'tasks', 'register', 'cron'],
            async run(ctx) {
                await ctx.froshToolsService.scheduledTasksRegister();
                ctx.notifySuccess({
                    message: ctx.t('frosh-tools.scheduledTasksRegisterSucceed'),
                });
            },
        },
    ];
}

/**
 * Clear the first cache pool whose name matches any candidate (case-insensitive).
 *
 * @param {CommandContext} ctx
 * @param {string[]} candidates
 */
async function clearCacheIfPresent(ctx, candidates) {
    const pools = await ctx.froshToolsService.getCacheInfo();
    const list = Array.isArray(pools) ? pools : [];
    const lower = candidates.map((name) => name.toLowerCase());

    const match = list.find((pool) =>
        lower.some(
            (candidate) =>
                String(pool?.name ?? '')
                    .toLowerCase()
                    .includes(candidate) ||
                String(pool?.type ?? '')
                    .toLowerCase()
                    .includes(candidate)
        )
    );

    if (!match?.name) {
        ctx.notifyError({
            message: ctx.t(
                'frosh-tools.commandPalette.actions.cachePoolNotFound',
                { names: candidates.join(', ') }
            ),
        });
        return;
    }

    await ctx.froshToolsService.clearCache(match.name);
    ctx.notifySuccess({
        message: ctx.t('frosh-tools.cacheCleared', { name: match.name }),
    });
}

/**
 * Rank + filter commands by a free-text query.
 *
 * @param {Array<CommandDefinition & { label: string, description: string, groupLabel: string }>} commands
 * @param {string} query
 * @returns {typeof commands}
 */
export function filterCommands(commands, query) {
    const term = String(query ?? '')
        .trim()
        .toLowerCase();

    if (!term) {
        return commands;
    }

    const tokens = term.split(/\s+/).filter(Boolean);

    return commands
        .map((command) => {
            const haystack = [
                command.label,
                command.description,
                command.groupLabel,
                command.id,
                ...(command.keywords ?? []),
            ]
                .join(' ')
                .toLowerCase();

            let score = 0;

            for (const token of tokens) {
                if (!haystack.includes(token)) {
                    return null;
                }

                if (command.label.toLowerCase().startsWith(token)) {
                    score += 8;
                } else if (command.label.toLowerCase().includes(token)) {
                    score += 5;
                } else if (
                    (command.keywords ?? []).some((keyword) =>
                        keyword.toLowerCase().includes(token)
                    )
                ) {
                    score += 3;
                } else {
                    score += 1;
                }
            }

            // Prefer exact label hits
            if (command.label.toLowerCase() === term) {
                score += 20;
            }

            return { command, score };
        })
        .filter(Boolean)
        .sort(
            (a, b) =>
                b.score - a.score ||
                a.command.label.localeCompare(b.command.label)
        )
        .map(({ command }) => command);
}
