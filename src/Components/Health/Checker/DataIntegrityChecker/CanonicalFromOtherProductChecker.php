<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class CanonicalFromOtherProductChecker implements DataIntegrityCheckerInterface, CheckerInterface
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
                INNER JOIN product cp
                    ON cp.id = p.canonical_product_id
                    AND cp.version_id = p.canonical_product_version_id
                WHERE p.version_id = :liveVersionId
                  AND COALESCE(cp.parent_id, cp.id) != COALESCE(p.parent_id, p.id)
                SQL,
            ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'product-canonical-other-family',
            'Products with a canonical product of another product',
            $count,
        ));
    }
}
