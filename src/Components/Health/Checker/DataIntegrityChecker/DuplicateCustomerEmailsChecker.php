<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class DuplicateCustomerEmailsChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'duplicate-customer-emails';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT c.id
        FROM customer c
        WHERE c.guest = 0
          AND EXISTS (SELECT 1
                      FROM customer other
                      WHERE other.guest = 0
                        AND other.id != c.id
                        AND other.email = c.email
                        AND CAST(LOWER(other.email) AS BINARY) = CAST(LOWER(c.email) AS BINARY)
                        AND (:isBoundToSalesChannel = 0
                            OR c.bound_sales_channel_id IS NULL
                            OR other.bound_sales_channel_id IS NULL
                            OR other.bound_sales_channel_id = c.bound_sales_channel_id))
        SQL;

    public function __construct(
        private readonly Connection $connection,
        private readonly SystemConfigService $systemConfigService,
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
            $this->getParameters(),
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Customers sharing an email address',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery($this->connection, CustomerDefinition::ENTITY_NAME, self::AFFECTED_QUERY, $this->getParameters(), [], $limit, $offset);
    }

    /**
     * @return array{isBoundToSalesChannel: int}
     */
    private function getParameters(): array
    {
        $isBoundToSalesChannel = $this->systemConfigService->getBool('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel');

        return ['isBoundToSalesChannel' => (int) $isBoundToSalesChannel];
    }
}
