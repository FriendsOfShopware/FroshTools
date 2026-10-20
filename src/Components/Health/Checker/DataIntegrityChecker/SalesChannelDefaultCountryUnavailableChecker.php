<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class SalesChannelDefaultCountryUnavailableChecker implements DataIntegrityCheckerInterface, CheckerInterface
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
                INNER JOIN country c ON c.id = sc.country_id
                WHERE sc.active = 1
                  AND sc.type_id IN (:typeIds)
                  AND (c.active = 0
                      OR c.shipping_available = 0
                      OR NOT EXISTS (SELECT 1
                                     FROM sales_channel_country scc
                                     WHERE scc.sales_channel_id = sc.id
                                       AND scc.country_id = sc.country_id))
                SQL,
            ['typeIds' => [
                Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_API),
            ]],
            ['typeIds' => ArrayParameterType::BINARY],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'sales-channel-default-country-unavailable',
            'Sales channels whose default country is not available for shipping',
            $count,
        ));
    }
}
