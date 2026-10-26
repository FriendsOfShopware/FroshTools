<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class MainVariantInvalidChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'product-main-variant-invalid';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT p.id
        FROM product p
        LEFT JOIN product mv
            ON mv.id = UNHEX(JSON_UNQUOTE(JSON_EXTRACT(p.variant_listing_config, '$.mainVariantId')))
            AND mv.version_id = p.version_id
        WHERE p.version_id = :liveVersionId
          AND p.parent_id IS NULL
          AND JSON_TYPE(JSON_EXTRACT(p.variant_listing_config, '$.mainVariantId')) = 'STRING'
          AND NOT (mv.parent_id <=> p.id)
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
            'Products with a main variant that is missing or of another product',
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
