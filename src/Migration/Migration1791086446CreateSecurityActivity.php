<?php

declare(strict_types=1);

namespace Frosh\Tools\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1791086446CreateSecurityActivity extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1791086446;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `frosh_tools_security_activity` (
                `id` BINARY(16) NOT NULL,
                `action` VARCHAR(100) NOT NULL,
                `actor_type` VARCHAR(32) NOT NULL,
                `actor_id` VARCHAR(255) DEFAULT NULL,
                `actor_name` VARCHAR(255) DEFAULT NULL,
                `level` SMALLINT UNSIGNED NOT NULL,
                `context` JSON NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx.ft_activity.created` (`created_at`, `id`),
                KEY `idx.ft_activity.action` (`action`, `created_at`, `id`),
                KEY `idx.ft_activity.actor_id` (`actor_id`, `created_at`),
                KEY `idx.ft_activity.actor_name` (`actor_name`, `created_at`),
                KEY `idx.ft_activity.actor_type` (`actor_type`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
