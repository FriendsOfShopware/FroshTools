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

class SalesChannelDefaultPaymentMethodChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'sales-channel-default-payment-unassigned';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT sc.id
        FROM sales_channel sc
        LEFT JOIN sales_channel_payment_method assigned
            ON assigned.sales_channel_id = sc.id
            AND assigned.payment_method_id = sc.payment_method_id
        WHERE sc.type_id IN (:typeIds)
          AND assigned.sales_channel_id IS NULL
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
            'Sales channels whose default payment method is not assigned',
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
