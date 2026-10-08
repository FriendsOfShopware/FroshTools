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
                WITH variant_options AS (SELECT p.id,
                                                p.parent_id,
                                                COUNT(*) AS option_count,
                                                SUM(CRC32(po.property_group_option_id)) AS checksum
                                         FROM product p
                                         INNER JOIN product_option po
                                             ON po.product_id = p.id
                                             AND po.product_version_id = p.version_id
                                         WHERE p.version_id = :liveVersionId
                                           AND p.parent_id IS NOT NULL
                                         GROUP BY p.id, p.parent_id)
                SELECT COUNT(DISTINCT variant.id)
                FROM variant_options variant
                INNER JOIN variant_options sibling
                    ON sibling.parent_id = variant.parent_id
                    AND sibling.option_count = variant.option_count
                    AND sibling.checksum = variant.checksum
                    AND sibling.id != variant.id
                WHERE NOT EXISTS (SELECT 1
                                  FROM product_option po
                                  WHERE po.product_id = variant.id
                                    AND po.product_version_id = :liveVersionId
                                    AND NOT EXISTS (SELECT 1
                                                    FROM product_option so
                                                    WHERE so.product_id = sibling.id
                                                      AND so.product_version_id = :liveVersionId
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
