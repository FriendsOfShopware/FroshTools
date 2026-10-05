<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Security\Activity\ActivityStore;
use Frosh\Tools\FroshTools;
use Frosh\Tools\Migration\Migration1791086446CreateSecurityActivity;
use Frosh\Tools\Migration\Migration1791089555AddActivityClientIp;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
class FroshToolsLifecycleTest extends TestCase
{
    public function testUninstallPreservesOrRemovesTheTimelineAccordingToUserDataChoice(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1791086446CreateSecurityActivity();
        $ipMigration = new Migration1791089555AddActivityClientIp();
        $migration->update($connection);
        $ipMigration->update($connection);
        $container = new ContainerBuilder();
        $container->set(Connection::class, $connection);
        $plugin = new FroshTools(true, \dirname(__DIR__));
        $plugin->setContainer($container);
        $migrations = static::createStub(MigrationCollection::class);
        $store = new ActivityStore($connection);
        $userId = Uuid::randomHex();
        $eventId = $store->append('user:update', 200, ['actorType' => 'user', 'userId' => $userId, 'clientIp' => '192.0.2.1'], new \DateTimeImmutable());

        try {
            $plugin->uninstall(new UninstallContext($plugin, Context::createDefaultContext(), '6.6.0.0', '3.15.0', $migrations, true));
            static::assertTrue($connection->createSchemaManager()->tablesExist(['frosh_tools_security_activity']));
            $events = $store->search(1, 25, '', $userId, null, null);
            static::assertSame(1, $events['total']);
            static::assertSame($eventId, $events['entries'][0]['id']);
            static::assertSame('192.0.2.1', $events['entries'][0]['context']['clientIp']);
            $plugin->uninstall(new UninstallContext($plugin, Context::createDefaultContext(), '6.6.0.0', '3.15.0', $migrations, false));
            static::assertFalse($connection->createSchemaManager()->tablesExist(['frosh_tools_security_activity']));
        } finally {
            $migration->update($connection);
            $ipMigration->update($connection);
        }
    }
}
