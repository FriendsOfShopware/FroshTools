<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class CustomerRequestedGroupMissingChecker implements DataIntegrityCheckerInterface, CheckerInterface
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
                FROM customer c
                WHERE c.requested_customer_group_id IS NOT NULL
                  AND NOT EXISTS (SELECT 1
                                  FROM customer_group g
                                  WHERE g.id = c.requested_customer_group_id)
                SQL,
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'customer-requested-group-missing',
            'Customers requesting a customer group that does not exist',
            $count,
        ));
    }
}
