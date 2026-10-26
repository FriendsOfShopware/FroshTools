<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class CurrencyFactorInvalidChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'currency-factor-invalid';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT id
        FROM currency
        WHERE factor <= 0
           OR (id = :defaultCurrencyId AND factor <> 1)
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
        $count = (int) $this->connection->fetchOne(
            \sprintf('SELECT COUNT(*) FROM (%s) affected', self::AFFECTED_QUERY),
            ['defaultCurrencyId' => Uuid::fromHexToBytes(Defaults::CURRENCY)],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Currencies with an invalid exchange rate factor',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery(
            $this->connection,
            'currency',
            self::AFFECTED_QUERY,
            ['defaultCurrencyId' => Uuid::fromHexToBytes(Defaults::CURRENCY)],
            [],
            $limit,
            $offset,
        );
    }
}
