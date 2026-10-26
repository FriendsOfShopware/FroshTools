<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\System\DeliveryTime\DeliveryTimeDefinition;

class DuplicateDeliveryTimesChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'duplicate-delivery-times';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT dt.id
        FROM delivery_time dt
        INNER JOIN (SELECT `min`, `max`, unit
                    FROM delivery_time
                    GROUP BY `min`, `max`, unit
                    HAVING COUNT(*) > 1) duplicated
            ON duplicated.min = dt.min
            AND duplicated.max = dt.max
            AND duplicated.unit = dt.unit
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
        $count = AffectedEntities::countRows($this->connection, self::AFFECTED_QUERY);

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Delivery times with the same range and unit',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery($this->connection, DeliveryTimeDefinition::ENTITY_NAME, self::AFFECTED_QUERY, [], [], $limit, $offset);
    }
}
