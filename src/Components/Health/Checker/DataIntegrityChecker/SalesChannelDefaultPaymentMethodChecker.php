<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class SalesChannelDefaultPaymentMethodChecker implements DataIntegrityCheckerInterface, CheckerInterface
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
                LEFT JOIN sales_channel_payment_method assigned
                    ON assigned.sales_channel_id = sc.id
                    AND assigned.payment_method_id = sc.payment_method_id
                WHERE assigned.sales_channel_id IS NULL
                SQL,
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'sales-channel-default-payment-unassigned',
            'Sales channels whose default payment method is not assigned',
            $count,
        ));
    }
}
