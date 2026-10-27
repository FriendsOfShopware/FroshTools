<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductPriceTiersInvalidChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'product-price-tiers-invalid';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT DISTINCT tiers.product_id AS id
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
        SQL;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function getAffectedEntity(): string
    {
        return ProductDefinition::ENTITY_NAME;
    }

    public function collect(HealthCollection $collection): void
    {
        $count = AffectedEntities::countRows(
            $this->connection,
            self::AFFECTED_QUERY,
            ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Products with gaps or overlaps in their advanced prices',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery(
            $this->connection,
            ProductDefinition::ENTITY_NAME,
            self::AFFECTED_QUERY,
            ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            [],
            $limit,
            $offset,
        );
    }
}
