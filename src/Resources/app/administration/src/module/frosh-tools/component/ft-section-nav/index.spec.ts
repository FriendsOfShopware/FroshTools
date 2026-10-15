import { describe, expect, it } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import {
    flushPromises,
    mountShopwareComponent,
} from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { resolveSection } from '../../section-navigation.ts';
import './index.ts';

const items = [
    { key: 'runtime', label: 'PHP runtime' },
    { key: 'database', label: 'Database' },
];

describe('tool section navigation', () => {
    it('uses links, keeps other query parameters, and supports Back', async () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                {
                    path: '/statistics',
                    name: 'statistics',
                    component: { template: '<div />' },
                },
            ],
        });
        await router.push('/statistics?section=runtime&source=overview');
        await router.isReady();
        const wrapper = await mountShopwareComponent('ft-section-nav', {
            props: { items, active: 'runtime', label: 'Statistics' },
            global: {
                plugins: [router],
                mocks: { $route: router.currentRoute.value },
            },
        });
        expect(wrapper.findAll('[aria-current="page"]')).toHaveLength(1);
        expect(wrapper.find('nav').attributes('aria-label')).toBe('Statistics');
        await wrapper.findAll('a')[1].trigger('click');
        await flushPromises();
        expect(router.currentRoute.value.query).toEqual({
            section: 'database',
            source: 'overview',
        });
        const back = new Promise<void>((resolve) => {
            const remove = router.afterEach(() => {
                remove();
                resolve();
            });
        });
        router.back();
        await back;
        expect(router.currentRoute.value.query.section).toBe('runtime');
        wrapper.unmount();
    });

    it.each([undefined, 'missing', ['database'], 'console'])(
        'falls back for unavailable section %s',
        (requested) => {
            expect(resolveSection(requested, items)).toBe('runtime');
        }
    );
});
