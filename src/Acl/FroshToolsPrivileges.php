<?php

declare(strict_types=1);

namespace Frosh\Tools\Acl;

/**
 * Privilege strings used by API routes and the Administration ACL mapping.
 *
 * Area suffixes follow Shopware's entity convention (`:read` / `:update`) so
 * roles can grant view access without also granting destructive actions.
 */
final class FroshToolsPrivileges
{
    public const READ = 'frosh_tools:read';

    public const CACHE_READ = 'frosh_tools_cache:read';
    public const CACHE_UPDATE = 'frosh_tools_cache:update';

    public const QUEUE_READ = 'frosh_tools_queue:read';
    public const QUEUE_UPDATE = 'frosh_tools_queue:update';

    public const SCHEDULED_TASK_READ = 'frosh_tools_scheduled_task:read';
    public const SCHEDULED_TASK_UPDATE = 'frosh_tools_scheduled_task:update';

    public const ELASTICSEARCH_READ = 'frosh_tools_elasticsearch:read';
    public const ELASTICSEARCH_UPDATE = 'frosh_tools_elasticsearch:update';

    public const LOGS_READ = 'frosh_tools_logs:read';

    public const SECURITY_READ = 'frosh_tools_security:read';
    public const SECURITY_UPDATE = 'frosh_tools_security:update';

    public const FASTLY_READ = 'frosh_tools_fastly:read';
    public const FASTLY_UPDATE = 'frosh_tools_fastly:update';

    public const SHOPMON_READ = 'frosh_tools_shopmon:read';
    public const SHOPMON_UPDATE = 'frosh_tools_shopmon:update';

    /**
     * @return list<string>
     */
    public static function allRead(): array
    {
        return [
            self::READ,
            self::CACHE_READ,
            self::QUEUE_READ,
            self::SCHEDULED_TASK_READ,
            self::ELASTICSEARCH_READ,
            self::LOGS_READ,
            self::SECURITY_READ,
            self::FASTLY_READ,
            self::SHOPMON_READ,
        ];
    }

    /**
     * @return list<string>
     */
    public static function allUpdate(): array
    {
        return [
            self::CACHE_UPDATE,
            self::QUEUE_UPDATE,
            self::SCHEDULED_TASK_UPDATE,
            self::ELASTICSEARCH_UPDATE,
            self::SECURITY_UPDATE,
            self::FASTLY_UPDATE,
            self::SHOPMON_UPDATE,
        ];
    }
}
