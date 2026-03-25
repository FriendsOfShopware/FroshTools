import './acl';
import './page/frosh-tools-webhook-list';
import './page/frosh-tools-webhook-detail';
import defaultSearchConfiguration from './default-search-configuration';

const { Module } = Shopware;

Shopware.Service('searchTypeService').upsertType('webhook', {
    entityName: 'webhook',
    placeholderSnippet: 'frosh-tools-webhook.general.placeholderSearchBar',
    listingRoute: 'frosh.tools.webhook.index',
});

Module.register('frosh-tools-webhook', {
    type: 'plugin',
    name: 'frosh-tools-webhook.general.title',
    title: 'frosh-tools-webhook.general.title',
    description: 'frosh-tools-webhook.general.description',
    color: '#303A4F',
    icon: 'regular-cog',
    entity: 'webhook',
    defaultSearchConfiguration,

    routes: {
        index: {
            component: 'frosh-tools-webhook-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.plugins',
                privilege: 'frosh_tools_webhook.viewer',
            },
        },
        detail: {
            component: 'frosh-tools-webhook-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'frosh.tools.webhook.index',
                privilege: 'frosh_tools_webhook.viewer',
            },
            props: {
                default(route) {
                    return { webhookId: route.params.id };
                },
            },
        },
        create: {
            component: 'frosh-tools-webhook-detail',
            path: 'create',
            meta: {
                parentPath: 'frosh.tools.webhook.index',
                privilege: 'frosh_tools_webhook.creator',
            },
        },
    },

    settingsItem: {
        group: 'plugins',
        to: 'frosh.tools.webhook.index',
        icon: 'regular-cog',
        privilege: 'frosh_tools_webhook.viewer',
    },
});
