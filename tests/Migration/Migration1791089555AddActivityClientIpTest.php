<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Migration;

use Frosh\Tools\Migration\Migration1791089555AddActivityClientIp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1791089555AddActivityClientIp::class)]
class Migration1791089555AddActivityClientIpTest extends TestCase
{
    public function testBackfillsExistingAddressesAndIsIdempotent(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1791089555AddActivityClientIp();
        $id = Uuid::randomBytes();
        $connection->executeStatement('ALTER TABLE frosh_tools_security_activity DROP INDEX `idx.ft_activity.client_ip`, DROP COLUMN client_ip');
        try {
            $connection->insert('frosh_tools_security_activity', ['id' => $id, 'action' => 'user:login', 'actor_type' => 'user', 'level' => 200, 'context' => '{"clientIp":"2001:db8::1"}', 'created_at' => '2026-10-04 00:00:00']);
            $migration->update($connection);
            $migration->update($connection);
            static::assertSame(inet_pton('2001:db8::1'), $connection->fetchOne('SELECT client_ip FROM frosh_tools_security_activity WHERE id = ?', [$id]));
            static::assertSame(['client_ip', 'created_at', 'id'], $connection->createSchemaManager()->listTableIndexes('frosh_tools_security_activity')['idx.ft_activity.client_ip']->getColumns());
        } finally {
            $migration->update($connection);
            $connection->delete('frosh_tools_security_activity', ['id' => $id]);
        }
    }
}
