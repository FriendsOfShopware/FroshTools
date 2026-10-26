<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\System\Country\CountryDefinition;

class CountryForcedStateWithoutStatesChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'country-forced-state-without-states';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT c.id
        FROM country c
        WHERE c.active = 1
          AND c.force_state_in_registration = 1
          AND EXISTS (SELECT 1 FROM sales_channel_country scc WHERE scc.country_id = c.id)
          AND NOT EXISTS (SELECT 1 FROM country_state cs WHERE cs.country_id = c.id AND cs.active = 1)
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
        $count = (int) $this->connection->fetchOne(\sprintf('SELECT COUNT(*) FROM (%s) affected', self::AFFECTED_QUERY));

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Countries requiring a state without active states',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery($this->connection, CountryDefinition::ENTITY_NAME, self::AFFECTED_QUERY, [], [], $limit, $offset);
    }
}
