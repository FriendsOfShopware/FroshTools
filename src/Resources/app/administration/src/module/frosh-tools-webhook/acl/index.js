Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'frosh_tools_webhook',
    roles: {
        viewer: {
            privileges: ['webhook:read', 'webhook_event_log:read', 'app:read'],
            dependencies: [],
        },
        editor: {
            privileges: ['webhook:update'],
            dependencies: ['frosh_tools_webhook.viewer'],
        },
        creator: {
            privileges: ['webhook:create'],
            dependencies: [
                'frosh_tools_webhook.viewer',
                'frosh_tools_webhook.editor',
            ],
        },
        deleter: {
            privileges: ['webhook:delete'],
            dependencies: ['frosh_tools_webhook.viewer'],
        },
    },
});
