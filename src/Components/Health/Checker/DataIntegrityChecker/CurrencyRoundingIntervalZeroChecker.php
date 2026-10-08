<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class CurrencyRoundingIntervalZeroChecker implements DataIntegrityCheckerInterface, CheckerInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $count = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(DISTINCT rounding.owner_id)
                FROM (
                    SELECT id AS owner_id, item_rounding AS config FROM currency
                    UNION ALL
                    SELECT id AS owner_id, total_rounding AS config FROM currency
                    UNION ALL
                    SELECT id AS owner_id, item_rounding AS config FROM currency_country_rounding
                    UNION ALL
                    SELECT id AS owner_id, total_rounding AS config FROM currency_country_rounding
                ) rounding
                WHERE rounding.config IS NOT NULL
                  AND COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(rounding.config, '$.decimals')) AS SIGNED), 0) <= 2
                  AND COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(rounding.config, '$.interval')) AS DECIMAL(30, 10)), 0) = 0
                SQL,
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'currency-rounding-interval-zero',
            'Currency roundings with an interval of zero',
            $count,
        ));
    }
}
