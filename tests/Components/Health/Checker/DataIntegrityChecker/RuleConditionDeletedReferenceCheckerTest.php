<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\RuleConditionDeletedReferenceChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(RuleConditionDeletedReferenceChecker::class)]
class RuleConditionDeletedReferenceCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'rule-condition-deleted-references';

    private RuleConditionDeletedReferenceChecker $checker;

    private string $ruleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(RuleConditionDeletedReferenceChecker::class);
        $this->connection->executeStatement('DELETE FROM rule_condition');

        $this->ruleId = Uuid::randomBytes();
        $this->connection->insert('rule', [
            'id' => $this->ruleId,
            'name' => 'Frosh data integrity check rule',
            'priority' => 1,
            'created_at' => self::now(),
        ]);
    }

    public function testReportsOkForExistingReferencesEmptyOperatorAndPartlyDeletedReferences(): void
    {
        $paymentMethodId = $this->fetchHexId('payment_method');

        $this->createCondition('paymentMethod', ['operator' => '=', 'paymentMethodIds' => [$paymentMethodId]]);
        $this->createCondition('paymentMethod', ['operator' => '!=', 'paymentMethodIds' => [Uuid::randomHex(), $paymentMethodId]]);
        $this->createCondition('customerTag', ['operator' => 'empty', 'identifiers' => [Uuid::randomHex()]]);
        $this->createCondition('currency', ['operator' => '=', 'currencyIds' => [Defaults::CURRENCY]]);
        $this->createCondition('customerOrderCount', ['operator' => '=', 'count' => 1]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsConditionsReferencingOnlyDeletedEntities(): void
    {
        $this->createCondition('paymentMethod', ['operator' => '=', 'paymentMethodIds' => [Uuid::randomHex(), Uuid::randomHex()]]);
        $this->createCondition('customerTag', ['operator' => '!=', 'identifiers' => [Uuid::randomHex()]]);
        $this->createCondition('customerShippingState', ['operator' => '=', 'stateIds' => [Uuid::randomHex()]]);
        $this->createCondition('currency', ['currencyIds' => [Uuid::randomHex()]]);
        $this->createCondition('cartLineItemInProductStream', ['operator' => '=', 'streamIds' => [Uuid::randomHex()]]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 5);
    }

    private function fetchHexId(string $table): string
    {
        return (string) $this->connection->fetchOne(\sprintf('SELECT LOWER(HEX(id)) FROM `%s` LIMIT 1', $table));
    }

    /**
     * @param array<string, mixed> $value
     */
    private function createCondition(string $type, array $value): void
    {
        $this->connection->insert('rule_condition', [
            'id' => Uuid::randomBytes(),
            'type' => $type,
            'rule_id' => $this->ruleId,
            'value' => json_encode($value, \JSON_THROW_ON_ERROR),
            'position' => 0,
            'created_at' => self::now(),
        ]);
    }
}
