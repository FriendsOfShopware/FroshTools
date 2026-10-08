<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductPriceTiersInvalidChecker implements DataIntegrityCheckerInterface, CheckerInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $count = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(DISTINCT tiers.product_id)
                FROM (
                    SELECT
                        product_id,
                        quantity_start,
                        quantity_end,
                        ROW_NUMBER() OVER (PARTITION BY product_id, rule_id ORDER BY quantity_start, quantity_end) AS position,
                        LAG(quantity_end) OVER (PARTITION BY product_id, rule_id ORDER BY quantity_start, quantity_end) AS previous_quantity_end
                    FROM product_price
                    WHERE product_version_id = :liveVersionId
                ) tiers
                WHERE (tiers.position = 1 AND tiers.quantity_start != 1)
                   OR tiers.quantity_end < tiers.quantity_start
                   OR (
                       tiers.position > 1
                       AND (tiers.previous_quantity_end IS NULL OR tiers.quantity_start != tiers.previous_quantity_end + 1)
                   )
                SQL,
            ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'product-price-tiers-invalid',
            'Products with gaps or overlaps in their advanced prices',
            $count,
        ));
    }
}
