<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class CustomerRequestedGroupMissingChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'customer-requested-group-missing';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT c.id
        FROM customer c
        WHERE c.requested_customer_group_id IS NOT NULL
          AND NOT EXISTS (SELECT 1
                          FROM customer_group g
                          WHERE g.id = c.requested_customer_group_id)
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
            'Customers requesting a customer group that does not exist',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery($this->connection, 'customer', self::AFFECTED_QUERY, [], [], $limit, $offset);
    }
}
