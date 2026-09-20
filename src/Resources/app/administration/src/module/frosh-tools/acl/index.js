import { PRIVILEGE } from './privileges';

const privileges = Shopware.Service('privileges');

function registerArea({
    key,
    viewer,
    editor = null,
    extraViewer = [],
    extraEditor = [],
}) {
    const roles = {
        viewer: {
            privileges: [viewer, PRIVILEGE.READ, ...extraViewer],
            dependencies: [],
        },
    };

    if (editor) {
        roles.editor = {
            privileges: [editor, ...extraEditor],
            dependencies: [`${key}.viewer`],
        };
    }

    privileges.addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'settings',
        key,
        roles,
    });
}

// Overview: health, performance, statistics, feature flags, state machines.
privileges.addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'frosh_tools',
    roles: {
        viewer: {
            privileges: [
                PRIVILEGE.READ,
                'state_machine:read',
                'state_machine_state:read',
                'state_machine_transition:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                PRIVILEGE.CACHE_UPDATE,
                PRIVILEGE.QUEUE_UPDATE,
                PRIVILEGE.SCHEDULED_TASK_UPDATE,
                PRIVILEGE.ELASTICSEARCH_UPDATE,
                PRIVILEGE.SECURITY_UPDATE,
                PRIVILEGE.FASTLY_UPDATE,
                PRIVILEGE.SHOPMON_UPDATE,
                PRIVILEGE.APPS_UPDATE,
            ],
            dependencies: [
                'frosh_tools.viewer',
                'frosh_tools_cache.viewer',
                'frosh_tools_queue.viewer',
                'frosh_tools_scheduled_task.viewer',
                'frosh_tools_elasticsearch.viewer',
                'frosh_tools_logs.viewer',
                'frosh_tools_security.viewer',
                'frosh_tools_fastly.viewer',
                'frosh_tools_shopmon.viewer',
                'frosh_tools_apps.viewer',
            ],
        },
    },
});

registerArea({
    key: 'frosh_tools_cache',
    viewer: PRIVILEGE.CACHE_READ,
    editor: PRIVILEGE.CACHE_UPDATE,
    extraEditor: ['sales_channel:read', 'theme:read', 'theme:update'],
});

registerArea({
    key: 'frosh_tools_queue',
    viewer: PRIVILEGE.QUEUE_READ,
    editor: PRIVILEGE.QUEUE_UPDATE,
});

registerArea({
    key: 'frosh_tools_scheduled_task',
    viewer: PRIVILEGE.SCHEDULED_TASK_READ,
    editor: PRIVILEGE.SCHEDULED_TASK_UPDATE,
    extraViewer: ['scheduled_task:read'],
    extraEditor: ['scheduled_task:update'],
});

registerArea({
    key: 'frosh_tools_elasticsearch',
    viewer: PRIVILEGE.ELASTICSEARCH_READ,
    editor: PRIVILEGE.ELASTICSEARCH_UPDATE,
});

registerArea({
    key: 'frosh_tools_logs',
    viewer: PRIVILEGE.LOGS_READ,
});

registerArea({
    key: 'frosh_tools_security',
    viewer: PRIVILEGE.SECURITY_READ,
    editor: PRIVILEGE.SECURITY_UPDATE,
});

registerArea({
    key: 'frosh_tools_fastly',
    viewer: PRIVILEGE.FASTLY_READ,
    editor: PRIVILEGE.FASTLY_UPDATE,
});

registerArea({
    key: 'frosh_tools_shopmon',
    viewer: PRIVILEGE.SHOPMON_READ,
    editor: PRIVILEGE.SHOPMON_UPDATE,
});

registerArea({
    key: 'frosh_tools_apps',
    viewer: PRIVILEGE.APPS_READ,
    editor: PRIVILEGE.APPS_UPDATE,
});
