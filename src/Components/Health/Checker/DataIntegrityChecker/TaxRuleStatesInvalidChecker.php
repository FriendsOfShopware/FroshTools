<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Tax\TaxRuleType\IndividualStatesRuleTypeFilter;

class TaxRuleStatesInvalidChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'tax-rule-states-invalid';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT tr.tax_id AS id
        FROM tax_rule tr
        INNER JOIN tax_rule_type trt ON trt.id = tr.tax_rule_type_id
        WHERE trt.technical_name = :technicalName
          AND tr.id NOT IN (SELECT matching.id
                            FROM tax_rule matching
                            CROSS JOIN JSON_TABLE(matching.data, '$.states[*]' COLUMNS (state_id VARCHAR(255) PATH '$')) state_reference
                            INNER JOIN country_state cs
                                ON cs.country_id = matching.country_id
                                AND CAST(LOWER(HEX(cs.id)) AS BINARY) = CAST(state_reference.state_id AS BINARY))
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
        $count = AffectedEntities::countRows(
            $this->connection,
            self::AFFECTED_QUERY,
            ['technicalName' => IndividualStatesRuleTypeFilter::TECHNICAL_NAME],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Tax rules for individual states that match no state of their country',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery(
            $this->connection,
            TaxDefinition::ENTITY_NAME,
            self::AFFECTED_QUERY,
            ['technicalName' => IndividualStatesRuleTypeFilter::TECHNICAL_NAME],
            [],
            $limit,
            $offset,
        );
    }
}
