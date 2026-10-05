<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Migration;

use Frosh\Tools\Migration\Migration1791086446CreateSecurityActivity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1791086446CreateSecurityActivity::class)]
class Migration1791086446CreateSecurityActivityTest extends TestCase
{
    public function testMigrationIsIdempotentAndCreatesFilteringIndexes(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1791086446CreateSecurityActivity();
        $connection->executeStatement('RENAME TABLE frosh_tools_security_activity TO frosh_tools_security_activity_test_backup');

        try {
            $migration->update($connection);
            $id = Uuid::randomBytes();
            $clientIp = inet_pton('2001:db8::1');
            $connection->insert('frosh_tools_security_activity', [
                'id' => $id,
                'action' => 'user:login',
                'actor_type' => 'user',
                'client_ip' => $clientIp,
                'level' => 200,
                'context' => '{}',
                'created_at' => '2026-10-04 00:00:00',
            ]);
            $migration->update($connection);

            static::assertSame($clientIp, $connection->fetchOne('SELECT client_ip FROM frosh_tools_security_activity WHERE id = ?', [$id]));
            $schema = $connection->createSchemaManager();
            $column = $schema->introspectTable('frosh_tools_security_activity')->getColumn('client_ip');
            static::assertSame(16, $column->getLength());
            static::assertFalse($column->getNotnull());
            $indexes = $schema->listTableIndexes('frosh_tools_security_activity');
            static::assertSame(['action', 'created_at', 'id'], $indexes['idx.ft_activity.action']->getColumns());
            static::assertSame(['actor_name', 'created_at'], $indexes['idx.ft_activity.actor_name']->getColumns());
            static::assertSame(['created_at', 'id'], $indexes['idx.ft_activity.created']->getColumns());
            static::assertSame(['client_ip', 'created_at', 'id'], $indexes['idx.ft_activity.client_ip']->getColumns());
        } finally {
            $connection->executeStatement('DROP TABLE IF EXISTS frosh_tools_security_activity');
            $connection->executeStatement('RENAME TABLE frosh_tools_security_activity_test_backup TO frosh_tools_security_activity');
        }
    }
}
