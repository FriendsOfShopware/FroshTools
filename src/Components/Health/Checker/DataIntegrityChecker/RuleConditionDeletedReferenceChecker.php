<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class RuleConditionDeletedReferenceChecker implements DataIntegrityCheckerInterface, CheckerInterface
{
    /**
     * @var list<array{types: list<string>, property: string, table: string}>
     */
    private const REFERENCES = [
        ['types' => ['paymentMethod'], 'property' => 'paymentMethodIds', 'table' => 'payment_method'],
        ['types' => ['shippingMethod'], 'property' => 'shippingMethodIds', 'table' => 'shipping_method'],
        ['types' => ['customerCustomerGroup', 'customerRequestedGroup'], 'property' => 'customerGroupIds', 'table' => 'customer_group'],
        ['types' => ['customerBillingCountry', 'customerShippingCountry'], 'property' => 'countryIds', 'table' => 'country'],
        ['types' => ['customerBillingState', 'customerShippingState'], 'property' => 'stateIds', 'table' => 'country_state'],
        ['types' => ['salesChannel'], 'property' => 'salesChannelIds', 'table' => 'sales_channel'],
        ['types' => ['currency'], 'property' => 'currencyIds', 'table' => 'currency'],
        ['types' => ['language'], 'property' => 'languageIds', 'table' => 'language'],
        ['types' => ['customerTag', 'orderTag'], 'property' => 'identifiers', 'table' => 'tag'],
        ['types' => ['customerSalutation'], 'property' => 'salutationIds', 'table' => 'salutation'],
        ['types' => ['cartLineItemInProductStream'], 'property' => 'streamIds', 'table' => 'product_stream'],
    ];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $count = 0;
        foreach (self::REFERENCES as $reference) {
            $count += (int) $this->connection->fetchOne(
                \sprintf(
                    <<<'SQL'
                        SELECT COUNT(*)
                        FROM (
                            SELECT rc.id
                            FROM rule_condition rc
                            CROSS JOIN JSON_TABLE(rc.value, '$.%s[*]' COLUMNS (reference_id VARCHAR(255) PATH '$')) reference
                            LEFT JOIN `%s` target ON target.id = UNHEX(reference.reference_id)
                            WHERE rc.type IN (:types)
                              AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(rc.value, '$.operator')), '=') IN ('=', '!=')
                            GROUP BY rc.id
                            HAVING COUNT(target.id) = 0
                        ) dead_condition
                        SQL,
                    $reference['property'],
                    $reference['table'],
                ),
                ['types' => $reference['types']],
                ['types' => ArrayParameterType::STRING],
            );
        }

        $collection->add(DataIntegrityCheckResult::fromCount(
            'rule-condition-deleted-references',
            'Rule conditions referencing only deleted entities',
            $count,
        ));
    }
}
