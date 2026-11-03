<?php

declare(strict_types=1);

namespace Frosh\Tools\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1793678226RemoveShopmonIntegrationData extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793678226;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('DELETE FROM `system_config` WHERE `configuration_key` IN (?, ?)', [
            'FroshTools.config.shopmonIntegrationData',
            'FroshShopmon.config.integrationData',
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
