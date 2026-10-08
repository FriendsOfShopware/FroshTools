<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class SalesChannelDefaultShippingMethodChecker implements DataIntegrityCheckerInterface, CheckerInterface
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
                LEFT JOIN sales_channel_shipping_method assigned
                    ON assigned.sales_channel_id = sc.id
                    AND assigned.shipping_method_id = sc.shipping_method_id
                WHERE assigned.sales_channel_id IS NULL
                SQL,
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'sales-channel-default-shipping-unassigned',
            'Sales channels whose default shipping method is not assigned',
            $count,
        ));
    }
}
