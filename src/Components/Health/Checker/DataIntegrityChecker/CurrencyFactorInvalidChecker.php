<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class CurrencyFactorInvalidChecker implements DataIntegrityCheckerInterface, CheckerInterface
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
                FROM currency
                WHERE factor <= 0
                   OR (id = :defaultCurrencyId AND factor <> 1)
                SQL,
            ['defaultCurrencyId' => Uuid::fromHexToBytes(Defaults::CURRENCY)],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'currency-factor-invalid',
            'Currencies with an invalid exchange rate factor',
            $count,
        ));
    }
}
