<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\System\Currency\CurrencyDefinition;

class CurrencyRoundingIntervalZeroChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'currency-rounding-interval-zero';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT rounding.currency_id AS id
        FROM (
            SELECT id AS currency_id, item_rounding AS config FROM currency
            UNION ALL
            SELECT id AS currency_id, total_rounding AS config FROM currency
            UNION ALL
            SELECT currency_id, item_rounding AS config FROM currency_country_rounding
            UNION ALL
            SELECT currency_id, total_rounding AS config FROM currency_country_rounding
        ) rounding
        WHERE rounding.config IS NOT NULL
          AND COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(rounding.config, '$.decimals')) AS SIGNED), 0) <= 2
          AND COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(rounding.config, '$.interval')) AS DECIMAL(30, 10)), 0) = 0
        SQL;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function getAffectedEntity(): string
    {
        return CurrencyDefinition::ENTITY_NAME;
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
            self::ID,
            'Currency roundings with an interval of zero',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery($this->connection, CurrencyDefinition::ENTITY_NAME, self::AFFECTED_QUERY, [], [], $limit, $offset);
    }
}
