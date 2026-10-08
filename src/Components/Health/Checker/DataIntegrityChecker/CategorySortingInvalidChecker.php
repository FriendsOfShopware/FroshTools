<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class CategorySortingInvalidChecker implements DataIntegrityCheckerInterface, CheckerInterface
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
                FROM category c
                INNER JOIN category after_category
                    ON after_category.id = c.after_category_id
                    AND after_category.version_id = c.after_category_version_id
                WHERE c.version_id = :liveVersionId
                  AND NOT (after_category.parent_id <=> c.parent_id)
                SQL,
            ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'category-sorting-invalid',
            'Categories sorted after a category of another parent',
            $count,
        ));
    }
}
