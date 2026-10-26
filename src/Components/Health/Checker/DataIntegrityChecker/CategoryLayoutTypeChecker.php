<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class CategoryLayoutTypeChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'category-layout-wrong-type';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT c.id
        FROM category c
        INNER JOIN cms_page cp
            ON cp.id = c.cms_page_id
            AND cp.version_id = c.cms_page_version_id
        WHERE c.version_id = :liveVersionId
          AND cp.type = 'product_detail'
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
            'Categories with a product page layout',
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
