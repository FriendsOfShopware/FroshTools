import { describe, expect, it, vi } from 'vitest';
import { mountShopwareComponent } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { FT_STUBS } from '../../../../../test/helpers';
import './index';

function setFroshToolsSettings(settings) {
    try {
        const store = Shopware.Store.get('context');
        store.app.config.settings = {
            ...(store.app.config.settings ?? {}),
            froshTools: settings,
        };
        return;
    } catch {
        /* 6.6 Vuex */
    }

    const state = Shopware.State.get('context');
    state.app.config.settings = {
        ...(state.app.config.settings ?? {}),
        froshTools: settings,
    };
}

async function createWrapper({
    routeName = 'frosh.tools.index.cache',
    privileges = null,
} = {}) {
    setFroshToolsSettings({
        fastlyEnabled: true,
        logsEnabled: true,
        elasticsearchEnabled: true,
    });

    return mountShopwareComponent('frosh-tools-index', {
        global: {
            stubs: {
                ...FT_STUBS,
                'sw-search-bar': true,
                'router-link': {
                    template: '<a><slot /></a>',
                    props: ['to'],
                },
                'router-view': true,
            },
            mocks: {
                $route: { name: routeName },
                $createTitle: () => 'Tools',
            },
            provide: {
                froshToolsService: {},
                acl: {
                    can: (privilege) =>
                        privileges ? privileges.includes(privilege) : true,
                },
            },
        },
    });
}

describe('frosh-tools-index', () => {
    it('exposes the shared search term and clears it when the tab changes', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onSearch('http_cache');
        expect(wrapper.vm.searchTerm).toBe('http_cache');
        expect(wrapper.vm.searchTab.type).toBe('frosh_tools_cache');

        wrapper.vm.$route.name = 'frosh.tools.index.queue';
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.searchTerm).toBe('');
    });

    it('includes optional Fastly, logs and Elasticsearch nav items when enabled', async () => {
        const wrapper = await createWrapper();

        const routes = wrapper.vm.navGroups.flatMap((group) =>
            group.items.map((item) => item.route)
        );

        expect(routes).toContain('frosh.tools.index.fastly');
        expect(routes).toContain('frosh.tools.index.logs');
        expect(routes).toContain('frosh.tools.index.elasticsearch');
    });

    it('filters navigation by ACL privileges', async () => {
        const wrapper = await createWrapper({
            privileges: ['frosh_tools:read'],
        });

        const routes = wrapper.vm.navGroups.flatMap((group) =>
            group.items.map((item) => item.route)
        );

        expect(routes).toContain('frosh.tools.index.index');
        expect(routes).not.toContain('frosh.tools.index.cache');
        expect(routes).not.toContain('frosh.tools.index.security');
    });
});
