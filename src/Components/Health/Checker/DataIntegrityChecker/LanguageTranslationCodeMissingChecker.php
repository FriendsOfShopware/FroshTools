<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class LanguageTranslationCodeMissingChecker implements DataIntegrityCheckerInterface, CheckerInterface
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
                FROM language l
                LEFT JOIN language parent ON parent.id = l.parent_id
                WHERE l.translation_code_id IS NULL
                  AND parent.translation_code_id IS NULL
                SQL,
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'language-translation-code-missing',
            'Languages without their own or an inherited translation code',
            $count,
        ));
    }
}
