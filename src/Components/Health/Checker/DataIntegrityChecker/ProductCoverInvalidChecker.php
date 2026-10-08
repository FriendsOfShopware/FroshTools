<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductCoverInvalidChecker implements DataIntegrityCheckerInterface, CheckerInterface
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
                LEFT JOIN product_media pm
                    ON pm.id = p.product_media_id
                    AND pm.version_id = p.version_id
                WHERE p.version_id = :liveVersionId
                  AND p.product_media_id IS NOT NULL
                  AND (pm.id IS NULL OR NOT (pm.product_id = p.id OR pm.product_id <=> p.parent_id))
                SQL,
            ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'product-cover-invalid',
            'Products with a missing cover or the cover of another product',
            $count,
        ));
    }
}
