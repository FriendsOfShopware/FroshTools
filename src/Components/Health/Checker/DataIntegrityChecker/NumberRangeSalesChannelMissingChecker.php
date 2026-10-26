<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class NumberRangeSalesChannelMissingChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'number-range-sales-channel-missing';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT sc.id
        FROM sales_channel sc
        WHERE sc.active = 1
          AND sc.type_id IN (:typeIds)
          AND EXISTS (SELECT 1
                      FROM number_range_type nrt
                      WHERE nrt.technical_name IS NOT NULL
                        AND NOT EXISTS (SELECT 1
                                        FROM number_range nr
                                        LEFT JOIN number_range_sales_channel nrsc ON nrsc.number_range_id = nr.id
                                        WHERE nr.type_id = nrt.id
                                          AND (nrsc.sales_channel_id = sc.id OR nrt.global = 1 OR nr.global = 1)))
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
            ['typeIds' => $this->getSalesChannelTypeIds()],
            ['typeIds' => ArrayParameterType::BINARY],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Number range types without a number range for a sales channel',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery(
            $this->connection,
            SalesChannelDefinition::ENTITY_NAME,
            self::AFFECTED_QUERY,
            ['typeIds' => $this->getSalesChannelTypeIds()],
            ['typeIds' => ArrayParameterType::BINARY],
            $limit,
            $offset,
        );
    }

    /**
     * @return list<string>
     */
    private function getSalesChannelTypeIds(): array
    {
        return [
            Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
            Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_API),
        ];
    }
}
