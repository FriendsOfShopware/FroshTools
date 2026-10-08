<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class MainVariantInvalidChecker implements DataIntegrityCheckerInterface, CheckerInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $count = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM product p
                LEFT JOIN product mv
                    ON mv.id = UNHEX(JSON_UNQUOTE(JSON_EXTRACT(p.variant_listing_config, '$.mainVariantId')))
                    AND mv.version_id = p.version_id
                WHERE p.version_id = :liveVersionId
                  AND p.parent_id IS NULL
                  AND JSON_TYPE(JSON_EXTRACT(p.variant_listing_config, '$.mainVariantId')) = 'STRING'
                  AND NOT (mv.parent_id <=> p.id)
                SQL,
            ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'product-main-variant-invalid',
            'Products with a main variant that is missing or of another product',
            $count,
        ));
    }
}
