<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class NumberRangeSalesChannelMissingChecker implements DataIntegrityCheckerInterface, CheckerInterface
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
                CROSS JOIN number_range_type nrt
                WHERE sc.active = 1
                  AND sc.type_id IN (:typeIds)
                  AND nrt.technical_name IS NOT NULL
                  AND NOT EXISTS (SELECT 1
                                  FROM number_range nr
                                  LEFT JOIN number_range_sales_channel nrsc ON nrsc.number_range_id = nr.id
                                  WHERE nr.type_id = nrt.id
                                    AND (nrsc.sales_channel_id = sc.id OR nrt.global = 1 OR nr.global = 1))
                SQL,
            ['typeIds' => [
                Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_API),
            ]],
            ['typeIds' => ArrayParameterType::BINARY],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'number-range-sales-channel-missing',
            'Number range types without a number range for a sales channel',
            $count,
        ));
    }
}
