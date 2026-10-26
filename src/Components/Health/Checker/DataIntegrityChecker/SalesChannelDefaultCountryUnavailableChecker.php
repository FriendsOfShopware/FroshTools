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

class SalesChannelDefaultCountryUnavailableChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'sales-channel-default-country-unavailable';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT sc.id
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
            $this->getParams(),
            $this->getTypes(),
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Sales channels whose default country is not available for shipping',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery(
            $this->connection,
            SalesChannelDefinition::ENTITY_NAME,
            self::AFFECTED_QUERY,
            $this->getParams(),
            $this->getTypes(),
            $limit,
            $offset,
        );
    }

    /**
     * @return array{typeIds: list<string>}
     */
    private function getParams(): array
    {
        return ['typeIds' => [
            Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
            Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_API),
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private function getTypes(): array
    {
        return ['typeIds' => ArrayParameterType::BINARY];
    }
}
