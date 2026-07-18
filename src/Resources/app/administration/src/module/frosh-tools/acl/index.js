Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'additional_permissions',
    parent: null,
    key: 'frosh_tools',
    roles: {
        viewer: {
            privileges: ['frosh_tools:read'],
            dependencies: [],
        },
        editor: {
            privileges: ['frosh_tools:update'],
            dependencies: ['frosh_tools.viewer'],
        },
    },
});
