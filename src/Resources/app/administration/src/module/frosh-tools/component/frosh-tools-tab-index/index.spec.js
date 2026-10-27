import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { mountRegistered } from '../../../../../test/helpers.js';
import '../../../../mixin/sortable-table.js';
import '../../../../mixin/settings-result.js';
import './index.js';
import enGB from '../../snippet/en-GB.json';
import deDE from '../../snippet/de-DE.json';

const TECHNICAL_TITLES = [
    'Admin-Worker',
    'Elasticsearch',
    'PCRE-Jit',
    'WebProfilerBundle',
    'Zend Opcache',
];

function snippetLeaves(messages, path = []) {
    return Object.entries(messages).flatMap(([key, value]) =>
        typeof value === 'string'
            ? [[[...path, key].join('.'), value]]
            : snippetLeaves(value, [...path, key])
    );
}

async function createWrapper(service) {
    return mountRegistered('frosh-tools-tab-index', {
        provide: {
            froshToolsService: service,
        },
    });
}

describe('frosh-tools-tab-index', () => {
    it('loads health and performance status on creation', async () => {
        const service = {
            healthStatus: vi.fn().mockResolvedValue([{ id: 'php' }]),
            performanceStatus: vi.fn().mockResolvedValue([]),
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
            healthStatus: vi
                .fn()
                .mockRejectedValue(new Error('Request failed')),
            performanceStatus: vi.fn().mockResolvedValue([]),
        };

        const wrapper = await createWrapper(service);
        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.vm.loadError).toBe('Request failed');

        await wrapper.vm.$nextTick();
        expect(wrapper.find('ft-hero-state-stub').exists()).toBe(true);
    });

    it('recovers when retrying after a failure', async () => {
        const service = {
            healthStatus: vi
                .fn()
                .mockRejectedValueOnce(new Error('Request failed'))
                .mockResolvedValue([{ id: 'php' }]),
            performanceStatus: vi.fn().mockResolvedValue([]),
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

    it('maps health states to pill variants and recommendation info', async () => {
        const wrapper = await createWrapper({
            healthStatus: vi.fn().mockResolvedValue([]),
            performanceStatus: vi.fn().mockResolvedValue([]),
        });
        await flushPromises();

        expect(wrapper.vm.pillVariant('STATE_ERROR')).toBe('danger');
        expect(wrapper.vm.pillVariant('STATE_WARNING')).toBe('warning');
        expect(wrapper.vm.hasInfo({ id: 'queue' })).toBe(true);
        expect(wrapper.vm.hasInfo({ id: 'unknown-check' })).toBe(false);
    });

    it('summarizes health and performance findings together', async () => {
        const wrapper = await createWrapper({
            healthStatus: vi
                .fn()
                .mockResolvedValue([
                    { state: 'STATE_OK' },
                    { state: 'STATE_WARNING' },
                ]),
            performanceStatus: vi
                .fn()
                .mockResolvedValue([{ state: 'STATE_ERROR' }]),
        });
        await flushPromises();

        const summary = wrapper.findAll('ft-hero-state-stub')[0];
        expect(summary.attributes('variant')).toBe('danger');
        expect(summary.attributes('body')).toBe(
            'Checks: 3 · Errors: 1 · Warnings: 1'
        );
    });

    it('does not show a summary while loading or when no checks were returned', async () => {
        let resolveHealth;
        const wrapper = await createWrapper({
            healthStatus: () =>
                new Promise((resolve) => {
                    resolveHealth = resolve;
                }),
            performanceStatus: vi.fn().mockResolvedValue([]),
        });
        expect(wrapper.find('.frosh-tab-index__summary').exists()).toBe(false);
        resolveHealth([]);
        await flushPromises();
        expect(wrapper.find('.frosh-tab-index__summary').exists()).toBe(false);
    });

    it('combines the translated recommendation with the code example', async () => {
        const wrapper = await createWrapper({
            healthStatus: vi.fn().mockResolvedValue([]),
            performanceStatus: vi.fn().mockResolvedValue([]),
        });
        await flushPromises();

        const english = enGB['frosh-tools'].tabs.index.recommendations;

        expect(wrapper.vm.recommendationFor({ id: 'queue' })).toEqual({
            description: english.queue.description,
            solution: english.queue.solution,
            code: expect.stringContaining('bin/console messenger:consume'),
        });
        expect(wrapper.vm.recommendationFor({ id: 'zend.assertions' })).toEqual(
            {
                description: english.zend.assertions.description,
                solution: english.zend.assertions.solution,
                code: '; php.ini\nzend.assertions=-1',
            }
        );
        expect(
            wrapper.vm.recommendationFor({ id: 'php-fpm-memory-peak' })
        ).toEqual({
            description: english['php-fpm-memory-peak'].description,
            solution: null,
            code: null,
        });
        expect(
            wrapper.vm.recommendationFor({ id: 'unknown-check' })
        ).toBeNull();
    });

    it('translates the info of every system status check into German', () => {
        const english = Object.fromEntries(
            snippetLeaves(enGB['frosh-tools'].tabs.index.recommendations)
        );
        const german = Object.fromEntries(
            snippetLeaves(deDE['frosh-tools'].tabs.index.recommendations)
        );

        expect(Object.keys(german)).toEqual(Object.keys(english));
        for (const [key, text] of Object.entries(german)) {
            if (key.endsWith('.title')) {
                continue;
            }
            expect(text, key).not.toBe(english[key]);
        }
    });

    it('titles the system status checks in both locales', () => {
        const titlesOf = (messages) =>
            Object.fromEntries(
                snippetLeaves(
                    messages['frosh-tools'].tabs.index.recommendations
                ).filter(([key]) => key.endsWith('.title'))
            );
        const english = titlesOf(enGB);
        const german = titlesOf(deDE);

        expect(Object.keys(english).length).toBeGreaterThan(0);
        expect(Object.keys(german)).toEqual(Object.keys(english));
        for (const [key, title] of Object.entries(german)) {
            if (TECHNICAL_TITLES.includes(english[key])) {
                expect(title, key).toBe(english[key]);
                continue;
            }
            expect(title, key).not.toBe(english[key]);
        }
    });

    it('shows the translated check title instead of the backend label', async () => {
        const english = enGB['frosh-tools'].tabs.index.recommendations;
        const wrapper = await createWrapper({
            healthStatus: vi.fn().mockResolvedValue([
                {
                    id: 'kerneldebug',
                    snippet: 'Kernel debug is active',
                    state: 'STATE_ERROR',
                    current: 'active',
                    recommended: 'not active',
                },
                {
                    id: 'third-party-check',
                    snippet: 'Third party label',
                    state: 'STATE_OK',
                    current: 'ok',
                    recommended: 'ok',
                },
            ]),
            performanceStatus: vi.fn().mockResolvedValue([
                {
                    id: 'queue.adapter',
                    snippet:
                        'The sync queue is not suitable for production environments',
                    state: 'STATE_WARNING',
                    current: 'sync',
                    recommended: 'redis or rabbitmq',
                },
            ]),
        });
        await flushPromises();

        expect(wrapper.vm.healthRows.map((row) => row.title)).toEqual([
            english.kerneldebug.title,
            'Third party label',
        ]);
        expect(wrapper.vm.performanceRows.map((row) => row.title)).toEqual([
            english.queue.adapter.title,
        ]);
        expect(wrapper.text()).toContain(english.kerneldebug.title);
        expect(wrapper.text()).toContain('Third party label');
        expect(wrapper.text()).toContain(english.queue.adapter.title);
        expect(wrapper.text()).not.toContain('Kernel debug is active');
        expect(wrapper.text()).not.toContain('The sync queue is not suitable');

        wrapper.vm.openInfo(wrapper.vm.performanceRows[0]);
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[role="dialog"]').attributes('title')).toBe(
            english.queue.adapter.title
        );
    });

    it('sorts the name column by the translated titles', async () => {
        const english = enGB['frosh-tools'].tabs.index.recommendations;
        const rows = [
            { id: 'php-version', snippet: 'A backend label' },
            { id: 'third-party-check', snippet: 'M backend label' },
            { id: 'database-info', snippet: 'Z backend label' },
        ];
        const wrapper = await createWrapper({
            healthStatus: vi.fn().mockResolvedValue(rows),
            performanceStatus: vi.fn().mockResolvedValue(rows),
        });
        await flushPromises();

        const expected = [
            english['database-info'].title,
            'M backend label',
            english['php-version'].title,
        ];
        expect(expected.slice().sort()).toEqual(expected);

        for (const [table, items] of [
            ['health', wrapper.vm.healthRows],
            ['performance', wrapper.vm.performanceRows],
        ]) {
            wrapper.vm.toggleSort('title', table);
            expect(
                wrapper.vm.sortRows(items, table).map((row) => row.title),
                table
            ).toEqual(expected);
        }

        await wrapper.vm.$nextTick();
        const names = wrapper
            .findAll('.ft-table__name')
            .map((cell) => cell.text());
        expect(names).toEqual([...expected, ...expected]);
    });
});
