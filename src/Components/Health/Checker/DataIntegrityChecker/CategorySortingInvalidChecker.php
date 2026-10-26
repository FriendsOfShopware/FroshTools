<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class CategorySortingInvalidChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'category-sorting-invalid';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT c.id
        FROM category c
        INNER JOIN category after_category
            ON after_category.id = c.after_category_id
            AND after_category.version_id = c.after_category_version_id
        WHERE c.version_id = :liveVersionId
          AND NOT (after_category.parent_id <=> c.parent_id)
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
        $count = AffectedEntities::countRows(
            $this->connection,
            self::AFFECTED_QUERY,
            ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Categories sorted after a category of another parent',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery(
            $this->connection,
            CategoryDefinition::ENTITY_NAME,
            self::AFFECTED_QUERY,
            ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            [],
            $limit,
            $offset,
        );
    }
}
