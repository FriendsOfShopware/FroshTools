<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class SalesChannelNavigationCategoryInactiveChecker implements DataIntegrityCheckerInterface, CheckerInterface
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
                FROM sales_channel sc
                INNER JOIN category c
                    ON c.id = sc.navigation_category_id
                    AND c.version_id = sc.navigation_category_version_id
                WHERE sc.type_id = :storefrontTypeId
                  AND sc.active = 1
                  AND c.active = 0
                SQL,
            ['storefrontTypeId' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT)],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'sales-channel-navigation-category-inactive',
            'Storefronts with an inactive main navigation category',
            $count,
        ));
    }
}
