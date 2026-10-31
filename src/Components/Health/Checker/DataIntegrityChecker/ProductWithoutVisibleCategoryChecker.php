<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductWithoutVisibleCategoryChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'product-without-visible-category';

    // Variants inherit categories and visibilities through the product.categories and product.visibilities
    // columns, so every variant is reported as the product that holds the category assignment.
    private const AFFECTED_QUERY = <<<'SQL'
        SELECT DISTINCT COALESCE(p.categories, p.id) AS id
        FROM product p
        LEFT JOIN product parent
            ON parent.id = p.parent_id
            AND parent.version_id = p.version_id
        INNER JOIN product_visibility pv
            ON pv.product_id = COALESCE(p.visibilities, p.id)
            AND pv.product_version_id = p.version_id
        INNER JOIN sales_channel sc
            ON sc.id = pv.sales_channel_id
        WHERE p.version_id = :liveVersionId
          AND COALESCE(p.active, parent.active) = 1
          AND pv.visibility = :visibilityAll
          AND sc.active = 1
          AND NOT EXISTS (
              SELECT 1
              FROM product_category pc
              INNER JOIN category c
                  ON c.id = pc.category_id
                  AND c.version_id = pc.category_version_id
              WHERE pc.product_id = COALESCE(p.categories, p.id)
                AND pc.product_version_id = p.version_id
                AND c.active = 1
                AND c.visible = 1
                AND (
                    c.id = sc.navigation_category_id
                    OR c.id = sc.service_category_id
                    OR c.id = sc.footer_category_id
                    OR COALESCE(c.path, '') LIKE CONCAT('%|', LOWER(HEX(sc.navigation_category_id)), '|%')
                    OR (sc.service_category_id IS NOT NULL AND COALESCE(c.path, '') LIKE CONCAT('%|', LOWER(HEX(sc.service_category_id)), '|%'))
                    OR (sc.footer_category_id IS NOT NULL AND COALESCE(c.path, '') LIKE CONCAT('%|', LOWER(HEX(sc.footer_category_id)), '|%'))
                )
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
            $this->getParameters(),
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Active products without an active and visible category in the sales channel',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery(
            $this->connection,
            ProductDefinition::ENTITY_NAME,
            self::AFFECTED_QUERY,
            $this->getParameters(),
            [],
            $limit,
            $offset,
        );
    }

    /**
     * @return array{liveVersionId: string, visibilityAll: int}
     */
    private function getParameters(): array
    {
        return [
            'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'visibilityAll' => ProductVisibilityDefinition::VISIBILITY_ALL,
        ];
    }
}
