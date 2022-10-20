Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'additional_permissions',
        parent: null,
        key: 'frosh_tools',
        roles: {
            frosh_tools: {
                privileges: [
                    'frosh_tools:read',
                ],
                dependencies: [],
            },
        },
    });
