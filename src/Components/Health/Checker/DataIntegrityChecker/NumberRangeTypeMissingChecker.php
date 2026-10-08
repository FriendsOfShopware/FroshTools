<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class NumberRangeTypeMissingChecker implements DataIntegrityCheckerInterface, CheckerInterface
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
                FROM number_range nr
                WHERE NOT EXISTS (SELECT 1 FROM number_range_type nrt WHERE nrt.id = nr.type_id)
                SQL,
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'number-range-type-missing',
            'Number ranges without a number range type',
            $count,
        ));
    }
}
