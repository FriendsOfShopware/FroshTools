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
            $column = $connection->fetchAssociative(<<<'SQL'
                SELECT CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'frosh_tools_security_activity' AND COLUMN_NAME = 'client_ip'
                SQL);
            static::assertIsArray($column);
            static::assertSame(16, (int) $column['CHARACTER_MAXIMUM_LENGTH']);
            static::assertSame('YES', $column['IS_NULLABLE']);
            $indexes = [
                'idx.ft_activity.action' => ['action', 'created_at', 'id'],
                'idx.ft_activity.actor_name' => ['actor_name', 'created_at'],
                'idx.ft_activity.actor_label' => ['actor_label', 'created_at', 'id'],
                'idx.ft_activity.created' => ['created_at', 'id'],
                'idx.ft_activity.client_ip' => ['client_ip', 'created_at', 'id'],
            ];
            foreach ($indexes as $index => $columns) {
                static::assertSame($columns, $connection->fetchFirstColumn(<<<'SQL'
                    SELECT COLUMN_NAME FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'frosh_tools_security_activity' AND INDEX_NAME = ?
                    ORDER BY SEQ_IN_INDEX
                    SQL, [$index]), $index);
            }
        } finally {
            $connection->executeStatement('DROP TABLE IF EXISTS frosh_tools_security_activity');
            $connection->executeStatement('RENAME TABLE frosh_tools_security_activity_test_backup TO frosh_tools_security_activity');
        }
    }

    public function testGeneratedActorLabelsUseFilteringIndex(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1791086446CreateSecurityActivity();
        $connection->executeStatement('RENAME TABLE frosh_tools_security_activity TO frosh_tools_security_activity_test_backup');

        try {
            $migration->update($connection);
            $actors = [
                ['actor_name' => 'alice', 'actor_id' => 'user-id', 'actor_type' => 'user'],
                ['actor_name' => '', 'actor_id' => 'user-id', 'actor_type' => 'user'],
                ['actor_name' => null, 'actor_id' => '', 'actor_type' => 'system'],
                ['actor_name' => null, 'actor_id' => null, 'actor_type' => 'anonymous'],
            ];
            foreach ($actors as $actor) {
                $connection->insert('frosh_tools_security_activity', [
                    'id' => Uuid::randomBytes(),
                    'action' => 'user:login',
                    'level' => 200,
                    'context' => '{}',
                    'created_at' => '2026-10-04 00:00:00',
                    ...$actor,
                ]);
            }

            $migration->update($connection);
            static::assertSame(['alice', 'anonymous', 'system', 'user-id'], $connection->fetchFirstColumn('SELECT actor_label FROM frosh_tools_security_activity ORDER BY actor_label'));

            $id = Uuid::randomBytes();
            $connection->insert('frosh_tools_security_activity', [
                'id' => $id,
                'action' => 'user:login',
                'actor_type' => 'user',
                'actor_name' => 'bob',
                'level' => 200,
                'context' => '{}',
                'created_at' => '2026-10-04 00:00:00',
            ]);
            static::assertSame('bob', $connection->fetchOne('SELECT actor_label FROM frosh_tools_security_activity WHERE id = ?', [$id]));
            $plan = $connection->fetchAssociative('EXPLAIN SELECT DISTINCT actor_label FROM frosh_tools_security_activity WHERE actor_label LIKE \'ali%\' ORDER BY actor_label LIMIT 51');
            static::assertIsArray($plan);
            static::assertSame('idx.ft_activity.actor_label', $plan['key']);
            $exactPlan = $connection->fetchAssociative('EXPLAIN SELECT id FROM frosh_tools_security_activity WHERE actor_label = \'alice\' ORDER BY created_at DESC, id DESC LIMIT 25');
            static::assertIsArray($exactPlan);
            static::assertSame('idx.ft_activity.actor_label', $exactPlan['key']);
        } finally {
            $connection->executeStatement('DROP TABLE IF EXISTS frosh_tools_security_activity');
            $connection->executeStatement('RENAME TABLE frosh_tools_security_activity_test_backup TO frosh_tools_security_activity');
        }
    }
}
