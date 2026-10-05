<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Migration;

use Frosh\Tools\Migration\Migration1791086446CreateSecurityActivity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

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
        $migration->update($connection);
        $migration->update($connection);
        $indexes = $connection->createSchemaManager()->listTableIndexes('frosh_tools_security_activity');
        static::assertSame(['action', 'created_at', 'id'], $indexes['idx.ft_activity.action']->getColumns());
        static::assertSame(['actor_name', 'created_at'], $indexes['idx.ft_activity.actor_name']->getColumns());
        static::assertSame(['created_at', 'id'], $indexes['idx.ft_activity.created']->getColumns());
    }
}
