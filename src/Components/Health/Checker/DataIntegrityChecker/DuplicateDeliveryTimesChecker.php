<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class DuplicateDeliveryTimesChecker implements DataIntegrityCheckerInterface, CheckerInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $count = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(dt.id)
                FROM delivery_time dt
                INNER JOIN (SELECT `min`, `max`, unit
                            FROM delivery_time
                            GROUP BY `min`, `max`, unit
                            HAVING COUNT(*) > 1) duplicated
                    ON duplicated.min = dt.min
                    AND duplicated.max = dt.max
                    AND duplicated.unit = dt.unit
                SQL,
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'duplicate-delivery-times',
            'Delivery times with the same range and unit',
            $count,
        ));
    }
}
