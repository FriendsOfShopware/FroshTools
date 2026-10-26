<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class InvalidFlowChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'flow-invalid';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT id
        FROM flow
        WHERE active = 1
          AND invalid = 1
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
            'Active flows with invalid actions',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery($this->connection, 'flow', self::AFFECTED_QUERY, [], [], $limit, $offset);
    }
}
