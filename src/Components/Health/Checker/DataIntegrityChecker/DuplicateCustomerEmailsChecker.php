<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class DuplicateCustomerEmailsChecker implements DataIntegrityCheckerInterface, CheckerInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $isBoundToSalesChannel = $this->systemConfigService->getBool('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel');

        $count = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
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
                SQL,
            ['isBoundToSalesChannel' => (int) $isBoundToSalesChannel],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'duplicate-customer-emails',
            'Customers sharing an email address',
            $count,
        ));
    }
}
