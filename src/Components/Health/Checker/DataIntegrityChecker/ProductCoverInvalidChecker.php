<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductCoverInvalidChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'product-cover-invalid';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT p.id
        FROM product p
        LEFT JOIN product_media pm
            ON pm.id = p.product_media_id
            AND pm.version_id = p.version_id
        WHERE p.version_id = :liveVersionId
          AND p.product_media_id IS NOT NULL
          AND (pm.id IS NULL
            OR NOT (pm.product_id = p.id
                OR pm.product_id <=> p.parent_id
                OR EXISTS (SELECT 1
                           FROM product_media own
                           WHERE own.product_id IN (p.id, p.parent_id)
                             AND own.product_version_id = p.version_id
                             AND own.media_id = pm.media_id)))
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
            'Products with a missing cover or the cover of another product',
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
