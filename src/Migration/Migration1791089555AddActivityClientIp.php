<?php

declare(strict_types=1);

namespace Frosh\Tools\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1791089555AddActivityClientIp extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1791089555;
    }

    public function update(Connection $connection): void
    {
        if (!$connection->createSchemaManager()->introspectTable('frosh_tools_security_activity')->hasColumn('client_ip')) {
            $connection->executeStatement('ALTER TABLE frosh_tools_security_activity ADD client_ip VARBINARY(16) DEFAULT NULL, ADD INDEX `idx.ft_activity.client_ip` (client_ip, created_at, id)');
        }
        $connection->executeStatement('UPDATE frosh_tools_security_activity SET client_ip = INET6_ATON(JSON_UNQUOTE(JSON_EXTRACT(context, \'$.clientIp\'))) WHERE client_ip IS NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
