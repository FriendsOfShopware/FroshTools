<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class VariantDuplicateOptionsChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'variant-duplicate-options';

    private const AFFECTED_QUERY = <<<'SQL'
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
        SELECT DISTINCT variant.id
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
        SQL;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function collect(HealthCollection $collection): void
    {
        $count = (int) $this->connection->fetchOne(
            \sprintf('SELECT COUNT(*) FROM (%s) affected', self::AFFECTED_QUERY),
            ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Variants with the same options as another variant',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery(
            $this->connection,
            'product',
            self::AFFECTED_QUERY,
            ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            [],
            $limit,
            $offset,
        );
    }
}
