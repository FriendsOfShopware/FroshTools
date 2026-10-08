<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ProductMainCategoryInvalidChecker implements DataIntegrityCheckerInterface, CheckerInterface
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.shopware_version%')]
        private readonly string $shopwareVersion,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $count = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM main_category mc
                INNER JOIN product p
                    ON p.id = mc.product_id
                    AND p.version_id = mc.product_version_id
                INNER JOIN category c
                    ON c.id = mc.category_id
                    AND c.version_id = mc.category_version_id
                INNER JOIN sales_channel sc
                    ON sc.id = mc.sales_channel_id
                WHERE mc.product_version_id = :liveVersionId
                  AND (
                      c.active = 0
                      OR (:hiddenCategoriesAreIgnored = 1 AND c.visible = 0)
                      OR NOT EXISTS (
                          SELECT 1
                          FROM product_category pc
                          WHERE pc.product_id = COALESCE(p.categories, p.id)
                            AND pc.product_version_id = p.version_id
                            AND pc.category_id = c.id
                      )
                      OR (
                          COALESCE(c.path, '') NOT LIKE CONCAT('%|', LOWER(HEX(sc.navigation_category_id)), '|%')
                          AND (sc.service_category_id IS NULL OR COALESCE(c.path, '') NOT LIKE CONCAT('%|', LOWER(HEX(sc.service_category_id)), '|%'))
                          AND (sc.footer_category_id IS NULL OR COALESCE(c.path, '') NOT LIKE CONCAT('%|', LOWER(HEX(sc.footer_category_id)), '|%'))
                      )
                  )
                SQL,
            [
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'hiddenCategoriesAreIgnored' => (int) (version_compare($this->shopwareVersion, '6.7.9.0', '>=') && version_compare($this->shopwareVersion, '6.7.15.0', '<')),
            ],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'product-main-category-invalid',
            'Main categories that cannot be used for the product',
            $count,
        ));
    }
}
