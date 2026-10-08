<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class VariantOptionConfiguratorSettingMissingChecker implements DataIntegrityCheckerInterface, CheckerInterface
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.shopware_version%')]
        private readonly string $shopwareVersion,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        if (version_compare($this->shopwareVersion, '6.7.14.0', '>=')) {
            return;
        }

        $count = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM product variant
                WHERE variant.version_id = :liveVersionId
                  AND variant.parent_id IS NOT NULL
                  AND EXISTS (
                      SELECT 1
                      FROM product_configurator_setting pcs
                      WHERE pcs.product_id = variant.parent_id
                        AND pcs.product_version_id = variant.version_id
                  )
                  AND EXISTS (
                      SELECT 1
                      FROM product_option po
                      WHERE po.product_id = variant.id
                        AND po.product_version_id = variant.version_id
                        AND NOT EXISTS (
                            SELECT 1
                            FROM product_configurator_setting pcs
                            WHERE pcs.product_id = variant.parent_id
                              AND pcs.product_version_id = variant.version_id
                              AND pcs.property_group_option_id = po.property_group_option_id
                        )
                  )
                SQL,
            ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'variant-option-configurator-setting-missing',
            'Variants with options missing in the configurator settings',
            $count,
        ));
    }
}
