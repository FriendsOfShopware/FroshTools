<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\System\Tax\TaxRuleType\IndividualStatesRuleTypeFilter;

class TaxRuleStatesInvalidChecker implements DataIntegrityCheckerInterface, CheckerInterface
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
                FROM tax_rule tr
                INNER JOIN tax_rule_type trt ON trt.id = tr.tax_rule_type_id
                WHERE trt.technical_name = :technicalName
                  AND tr.id NOT IN (SELECT matching.id
                                    FROM tax_rule matching
                                    CROSS JOIN JSON_TABLE(matching.data, '$.states[*]' COLUMNS (state_id VARCHAR(255) PATH '$')) state_reference
                                    INNER JOIN country_state cs
                                        ON cs.country_id = matching.country_id
                                        AND CAST(LOWER(HEX(cs.id)) AS BINARY) = CAST(state_reference.state_id AS BINARY))
                SQL,
            ['technicalName' => IndividualStatesRuleTypeFilter::TECHNICAL_NAME],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'tax-rule-states-invalid',
            'Tax rules for individual states that match no state of their country',
            $count,
        ));
    }
}
