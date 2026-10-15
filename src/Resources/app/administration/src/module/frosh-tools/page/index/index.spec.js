import { describe, expect, it, vi } from 'vitest';
import { mountShopwareComponent } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { FT_STUBS } from '../../../../../test/helpers.js';
import './index.js';

function setFroshToolsSettings(settings) {
    const apply = (context) => {
        context.app.config.settings = {
            ...(context.app.config.settings ?? {}),
            froshTools: settings,
        };
    };

    try {
        apply(Shopware.Store.get('context'));
    } catch {
        /* Shopware 6.6 uses Vuex */
    }

    try {
        apply(Shopware.State.get('context'));
    } catch {
        /* Store-only runtimes */
    }
}

async function createWrapper({
    routeName = 'frosh.tools.index.cache',
    privileges = null,
    query = {},
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
                    template:
                        '<a :class="{ \'is-active\': to.name === $route.name }"><slot /></a>',
                    props: ['to'],
                },
                'router-view': true,
            },
            mocks: {
                $route: { name: routeName, query },
                $router: { push: vi.fn() },
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
    it('keeps index search available when a restricted section falls back to indices', async () => {
        const options = {
            routeName: 'frosh.tools.index.elasticsearch',
            query: { section: 'console' },
        };
        const editor = await createWrapper(options);
        expect(editor.vm.searchTab).toBeNull();
        const viewer = await createWrapper({ ...options, privileges: [] });
        expect(viewer.vm.searchTab.type).toBe('frosh_tools_elasticsearch');
    });

    it('exposes the shared search term and clears it when the tab changes', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onSearch('http_cache');
        expect(wrapper.vm.searchTerm).toBe('http_cache');
        expect(wrapper.vm.searchTab.type).toBe('frosh_tools_cache');

        const queue = await createWrapper({
            routeName: 'frosh.tools.index.queue',
        });
        expect(queue.vm.searchTab.type).toBe('frosh_tools_queue');
        expect(queue.vm.searchTerm).toBe('');
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

    it('places Apps in Operations', async () => {
        const wrapper = await createWrapper();
        const appGroups = wrapper.vm.navGroups.filter((group) =>
            group.items.some((item) => item.route === 'frosh.tools.index.apps')
        );

        expect(appGroups.map((group) => group.labelKey)).toEqual([
            'frosh-tools.nav.operations',
        ]);
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
        expect(routes).not.toContain('frosh.tools.index.apps');
    });
    it('labels the navigation landmark and keeps every destination named', async () => {
        const wrapper = await createWrapper();
        const navigation = wrapper.find('nav.ft-sidebar');
        expect(navigation.attributes('aria-label')).toBe('Tools');
        expect(
            navigation.findAll('a').every((link) => link.text().length > 0)
        ).toBe(true);
    });
    it('reveals the selected destination when opening a deep link', async () => {
        const scrollIntoView = vi.fn();
        const original = Element.prototype.scrollIntoView;
        Element.prototype.scrollIntoView = scrollIntoView;
        try {
            const wrapper = await createWrapper({
                routeName: 'frosh.tools.index.fastly',
            });
            await wrapper.vm.$nextTick();
            expect(scrollIntoView).toHaveBeenCalledOnce();
            expect(scrollIntoView.mock.contexts[0].textContent.trim()).toBe(
                'Fastly'
            );
            wrapper.unmount();
        } finally {
            if (original) Element.prototype.scrollIntoView = original;
            else delete Element.prototype.scrollIntoView;
        }
    });
    it('offers every permitted tool in the mobile picker, including webhooks', async () => {
        const wrapper = await createWrapper();
        const picker = wrapper.find('#ft-tool-picker');
        expect(
            picker.find('option[value="frosh.tools.webhook.index"]').text()
        ).toBe('Webhooks');
        await picker.setValue('frosh.tools.index.logs');
        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'frosh.tools.index.logs',
        });
        wrapper.unmount();
        const restricted = await createWrapper({
            privileges: ['frosh_tools:read'],
        });
        expect(
            restricted
                .find('option[value="frosh.tools.webhook.index"]')
                .exists()
        ).toBe(false);
        restricted.vm.navigateToTool('frosh.tools.webhook.index');
        expect(restricted.vm.$router.push).not.toHaveBeenCalled();
        restricted.unmount();
    });
});
