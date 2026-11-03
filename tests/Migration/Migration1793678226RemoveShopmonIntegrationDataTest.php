<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Migration;

use Frosh\Tools\Migration\Migration1793678226RemoveShopmonIntegrationData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(Migration1793678226RemoveShopmonIntegrationData::class)]
class Migration1793678226RemoveShopmonIntegrationDataTest extends TestCase
{
    public function testRemovesStoredCredentialsAndPreservesOtherConfiguration(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $connection->beginTransaction();

        try {
            $keys = ['FroshTools.config.shopmonIntegrationData', 'FroshShopmon.config.integrationData'];
            $connection->executeStatement('DELETE FROM system_config WHERE configuration_key IN (?, ?)', $keys);
            $unrelatedId = Uuid::randomBytes();
            foreach ($keys as $key) {
                $connection->insert('system_config', [
                    'id' => Uuid::randomBytes(),
                    'configuration_key' => $key,
                    'configuration_value' => '{"_value":"old-credentials"}',
                    'created_at' => '2026-11-03 00:00:00',
                ]);
            }
            $connection->insert('system_config', [
                'id' => $unrelatedId,
                'configuration_key' => 'FroshTools.config.unrelatedTestSetting',
                'configuration_value' => '{"_value":"keep"}',
                'created_at' => '2026-11-03 00:00:00',
            ]);
            $migration = new Migration1793678226RemoveShopmonIntegrationData();
            $migration->update($connection);
            $migration->update($connection);

            static::assertSame([], $connection->fetchFirstColumn('SELECT id FROM system_config WHERE configuration_key IN (?, ?)', $keys));
            static::assertSame($unrelatedId, $connection->fetchOne('SELECT id FROM system_config WHERE id = ?', [$unrelatedId]));
        } finally {
            $connection->rollBack();
        }
    }
}
