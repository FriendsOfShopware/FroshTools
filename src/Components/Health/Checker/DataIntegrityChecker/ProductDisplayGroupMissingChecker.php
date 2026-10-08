<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductDisplayGroupMissingChecker implements DataIntegrityCheckerInterface, CheckerInterface
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
                WHERE p.version_id = :liveVersionId
                  AND p.display_group IS NULL
                  AND (
                      p.parent_id IS NOT NULL
                      OR NOT EXISTS (
                          SELECT 1
                          FROM product child
                          WHERE child.parent_id = p.id
                            AND child.version_id = p.version_id
                      )
                  )
                SQL,
            ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'product-display-group-missing',
            'Products hidden from listings by a missing display group',
            $count,
        ));
    }
}
