<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class VariantWithoutOptionsChecker implements DataIntegrityCheckerInterface, CheckerInterface
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
                  AND p.parent_id IS NOT NULL
                  AND NOT EXISTS (SELECT 1
                                  FROM product_option po
                                  WHERE po.product_id = p.id
                                    AND po.product_version_id = p.version_id)
                SQL,
            ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'variant-without-options',
            'Variants without options',
            $count,
        ));
    }
}
