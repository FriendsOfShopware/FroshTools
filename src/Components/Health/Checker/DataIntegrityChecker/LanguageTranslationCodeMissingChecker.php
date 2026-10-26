<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class LanguageTranslationCodeMissingChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'language-translation-code-missing';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT l.id
        FROM language l
        LEFT JOIN language parent ON parent.id = l.parent_id
        WHERE l.translation_code_id IS NULL
          AND parent.translation_code_id IS NULL
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
            'Languages without their own or an inherited translation code',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery($this->connection, 'language', self::AFFECTED_QUERY, [], [], $limit, $offset);
    }
}
