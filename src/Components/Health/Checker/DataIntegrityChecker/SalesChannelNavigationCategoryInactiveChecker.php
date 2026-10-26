<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class SalesChannelNavigationCategoryInactiveChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'sales-channel-navigation-category-inactive';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT sc.id AS sales_channel_id, c.id AS category_id
        FROM sales_channel sc
        INNER JOIN category c
            ON c.id = sc.navigation_category_id
            AND c.version_id = sc.navigation_category_version_id
        WHERE sc.type_id = :storefrontTypeId
          AND sc.active = 1
          AND c.active = 0
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
            ['storefrontTypeId' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT)],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Storefronts with an inactive main navigation category',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery(
            $this->connection,
            'category',
            \sprintf('SELECT affected.category_id AS id FROM (%s) affected', self::AFFECTED_QUERY),
            ['storefrontTypeId' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT)],
            [],
            $limit,
            $offset,
        );
    }
}
