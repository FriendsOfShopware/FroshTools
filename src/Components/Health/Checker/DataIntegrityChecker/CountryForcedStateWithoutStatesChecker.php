<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class CountryForcedStateWithoutStatesChecker implements DataIntegrityCheckerInterface, CheckerInterface
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
                FROM country c
                WHERE c.active = 1
                  AND c.force_state_in_registration = 1
                  AND EXISTS (SELECT 1 FROM sales_channel_country scc WHERE scc.country_id = c.id)
                  AND NOT EXISTS (SELECT 1 FROM country_state cs WHERE cs.country_id = c.id AND cs.active = 1)
                SQL,
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'country-forced-state-without-states',
            'Countries requiring a state without active states',
            $count,
        ));
    }
}
