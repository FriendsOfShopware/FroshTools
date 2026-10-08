<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class VariantDuplicateOptionsChecker implements DataIntegrityCheckerInterface, CheckerInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $count = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(DISTINCT variant.id)
                FROM product variant
                INNER JOIN product sibling
                    ON sibling.parent_id = variant.parent_id
                    AND sibling.version_id = variant.version_id
                    AND sibling.id != variant.id
                WHERE variant.version_id = :liveVersionId
                  AND variant.parent_id IS NOT NULL
                  AND EXISTS (SELECT 1
                              FROM product_option po
                              WHERE po.product_id = variant.id
                                AND po.product_version_id = variant.version_id)
                  AND (SELECT COUNT(*)
                       FROM product_option po
                       WHERE po.product_id = variant.id
                         AND po.product_version_id = variant.version_id)
                    = (SELECT COUNT(*)
                       FROM product_option so
                       WHERE so.product_id = sibling.id
                         AND so.product_version_id = sibling.version_id)
                  AND NOT EXISTS (SELECT 1
                                  FROM product_option po
                                  WHERE po.product_id = variant.id
                                    AND po.product_version_id = variant.version_id
                                    AND NOT EXISTS (SELECT 1
                                                    FROM product_option so
                                                    WHERE so.product_id = sibling.id
                                                      AND so.product_version_id = sibling.version_id
                                                      AND so.property_group_option_id = po.property_group_option_id))
                SQL,
            ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'variant-duplicate-options',
            'Variants with the same options as another variant',
            $count,
        ));
    }
}
