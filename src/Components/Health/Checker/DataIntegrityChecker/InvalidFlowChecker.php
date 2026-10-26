<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Content\Flow\FlowDefinition;

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
        $count = AffectedEntities::countRows($this->connection, self::AFFECTED_QUERY);

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Active flows with invalid actions',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery($this->connection, FlowDefinition::ENTITY_NAME, self::AFFECTED_QUERY, [], [], $limit, $offset);
    }
}
