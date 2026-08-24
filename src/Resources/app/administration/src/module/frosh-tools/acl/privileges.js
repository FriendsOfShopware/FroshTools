/**
 * Privilege strings — keep in sync with Frosh\Tools\Acl\FroshToolsPrivileges.
 */
export const PRIVILEGE = {
    READ: 'frosh_tools:read',

    CACHE_READ: 'frosh_tools_cache:read',
    CACHE_UPDATE: 'frosh_tools_cache:update',

    QUEUE_READ: 'frosh_tools_queue:read',
    QUEUE_UPDATE: 'frosh_tools_queue:update',

    SCHEDULED_TASK_READ: 'frosh_tools_scheduled_task:read',
    SCHEDULED_TASK_UPDATE: 'frosh_tools_scheduled_task:update',

    ELASTICSEARCH_READ: 'frosh_tools_elasticsearch:read',
    ELASTICSEARCH_UPDATE: 'frosh_tools_elasticsearch:update',

    LOGS_READ: 'frosh_tools_logs:read',

    SECURITY_READ: 'frosh_tools_security:read',
    SECURITY_UPDATE: 'frosh_tools_security:update',

    FASTLY_READ: 'frosh_tools_fastly:read',
    FASTLY_UPDATE: 'frosh_tools_fastly:update',

    SHOPMON_READ: 'frosh_tools_shopmon:read',
    SHOPMON_UPDATE: 'frosh_tools_shopmon:update',
};
