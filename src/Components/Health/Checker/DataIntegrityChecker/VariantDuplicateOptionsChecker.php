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
                SELECT COALESCE(SUM(duplicated.variants), 0)
                FROM (SELECT COUNT(*) AS variants
                      FROM (SELECT p.parent_id,
                                   GROUP_CONCAT(HEX(po.property_group_option_id) ORDER BY po.property_group_option_id) AS options
                            FROM product p
                            INNER JOIN product_option po
                                ON po.product_id = p.id
                                AND po.product_version_id = p.version_id
                            WHERE p.version_id = :liveVersionId
                              AND p.parent_id IS NOT NULL
                            GROUP BY p.id, p.parent_id) variant_options
                      GROUP BY variant_options.parent_id, variant_options.options
                      HAVING COUNT(*) > 1) duplicated
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
