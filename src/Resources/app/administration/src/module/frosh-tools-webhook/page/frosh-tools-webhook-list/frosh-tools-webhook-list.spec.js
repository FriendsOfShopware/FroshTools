import { describe, expect, it, vi } from 'vitest';
import {
    flushPromises,
    mountShopwareComponent,
} from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import './index';

async function createWrapper() {
    const webhooks = [{ id: 'webhook-id' }];
    Object.assign(webhooks, { total: 1 });

    const repository = {
        search: vi.fn().mockResolvedValue(webhooks),
    };

    const wrapper = await mountShopwareComponent('frosh-tools-webhook-list', {
        data() {
            return {
                disableRouteParams: true,
            };
        },
        global: {
            renderStubDefaultSlot: true,
            mocks: {
                $route: {
                    name: 'frosh.tools.webhook.index',
                    params: {},
                    query: {},
                    meta: {
                        $module: {
                            icon: 'regular-webhook',
                        },
                    },
                },
                $router: {
                    push: vi.fn(),
                    replace: vi.fn(),
                },
                $createTitle: () => 'Webhooks',
            },
            provide: {
                repositoryFactory: {
                    create: () => repository,
                },
                acl: {
                    can: () => true,
                },
                filterFactory: {
                    create: () => [],
                },
                searchRankingService: {
                    getSearchFieldsByEntity: () => ({}),
                },
                feature: {},
            },
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="search-bar"></slot>
                            <slot name="content"></slot>
                        </div>
                    `,
                },
                'sw-search-bar': {
                    name: 'sw-search-bar',
                    emits: ['search'],
                    template: '<div />',
                },
                'sw-card-view': {
                    template: '<div><slot /></div>',
                },
                'sw-card': {
                    template: `
                        <div>
                            <slot name="toolbar"></slot>
                            <slot name="grid"></slot>
                        </div>
                    `,
                },
                'sw-simple-search-field': {
                    name: 'sw-simple-search-field',
                    props: ['value'],
                    emits: ['search-term-change'],
                    template: '<div />',
                },
                'sw-entity-listing': true,
                'sw-empty-state': true,
            },
        },
    });

    await flushPromises();

    return { repository, wrapper };
}

describe('frosh-tools-webhook-list', () => {
    it('uses one search term for the regular and inline search fields', async () => {
        const { repository, wrapper } = await createWrapper();
        const regularSearch = wrapper.findComponent({
            name: 'sw-search-bar',
        });
        const inlineSearch = wrapper.findComponent({
            name: 'sw-simple-search-field',
        });

        regularSearch.vm.$emit('search', 'regular search');
        await flushPromises();

        expect(inlineSearch.props('value')).toBe('regular search');

        inlineSearch.vm.$emit('search-term-change', 'inline search');
        await flushPromises();

        const criteria = repository.search.mock.calls.at(-1)[0];

        expect(wrapper.vm.term).toBe('inline search');
        expect(wrapper.vm.page).toBe(1);
        expect(criteria.term).toBe('inline search');
    });
});
