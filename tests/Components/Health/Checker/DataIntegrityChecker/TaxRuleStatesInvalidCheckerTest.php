<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\TaxRuleStatesInvalidChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(TaxRuleStatesInvalidChecker::class)]
class TaxRuleStatesInvalidCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'tax-rule-states-invalid';

    private TaxRuleStatesInvalidChecker $checker;

    private string $taxId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(TaxRuleStatesInvalidChecker::class);
        $this->connection->executeStatement(
            'DELETE FROM tax_rule WHERE tax_rule_type_id IN (SELECT id FROM tax_rule_type WHERE technical_name = :technicalName)',
            ['technicalName' => 'individual_states'],
        );

        $this->taxId = $this->createTax();
    }

    public function testReportsOkForRulesWithAtLeastOneStateOfTheirCountry(): void
    {
        $countryId = $this->createCountry();
        $stateId = $this->createState($countryId);
        $otherStateId = $this->createState($this->createCountry());

        $this->createTaxRule('individual_states', $countryId, ['states' => [$stateId]]);
        $this->createTaxRule('individual_states', $countryId, ['states' => [$otherStateId, $stateId, Uuid::randomHex()]]);
        $this->createTaxRule('entire_country', $countryId, null);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
        static::assertAffectedEntities($this->checker, 'tax', []);
    }

    public function testCountsRulesMatchingNoStateOfTheirCountry(): void
    {
        $countryId = $this->createCountry();
        $stateId = $this->createState($countryId);
        $otherStateId = $this->createState($this->createCountry());

        $this->createTaxRule('individual_states', $countryId, ['states' => [$otherStateId]]);
        $this->createTaxRule('individual_states', $countryId, ['states' => [Uuid::randomHex()]]);
        $this->createTaxRule('individual_states', $countryId, ['states' => []]);
        $this->createTaxRule('individual_states', $countryId, null);
        $this->createTaxRule('individual_states', $countryId, ['states' => [strtoupper($stateId)]]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 5);
        static::assertAffectedEntities($this->checker, 'tax', [$this->taxId]);
    }

    public function testListsEveryTaxWithAnInvalidRuleOnce(): void
    {
        $countryId = $this->createCountry();
        $otherTaxId = $this->createTax();

        $this->createTaxRule('individual_states', $countryId, ['states' => [Uuid::randomHex()]]);
        $this->createTaxRule('individual_states', $countryId, ['states' => []]);
        $this->createTaxRule('individual_states', $countryId, null, $otherTaxId);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 3);
        static::assertAffectedEntities($this->checker, 'tax', [$this->taxId, $otherTaxId]);
    }

    private function createTax(): string
    {
        $id = Uuid::randomHex();

        $this->connection->insert('tax', [
            'id' => Uuid::fromHexToBytes($id),
            'tax_rate' => 7,
            'name' => 'Frosh data integrity check tax',
            'created_at' => self::now(),
        ]);

        return $id;
    }

    private function createCountry(): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('country', [
            'id' => $id,
            'iso' => 'XX',
            'created_at' => self::now(),
        ]);

        return $id;
    }

    private function createState(string $countryId): string
    {
        $id = Uuid::randomHex();

        $this->connection->insert('country_state', [
            'id' => Uuid::fromHexToBytes($id),
            'country_id' => $countryId,
            'short_code' => 'XX-' . $id,
            'created_at' => self::now(),
        ]);

        return $id;
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function createTaxRule(string $type, string $countryId, ?array $data, ?string $taxId = null): void
    {
        $this->connection->insert('tax_rule', [
            'id' => Uuid::randomBytes(),
            'tax_id' => Uuid::fromHexToBytes($taxId ?? $this->taxId),
            'tax_rule_type_id' => $this->connection->fetchOne('SELECT id FROM tax_rule_type WHERE technical_name = :technicalName', ['technicalName' => $type]),
            'country_id' => $countryId,
            'tax_rate' => 7,
            'data' => $data === null ? null : json_encode($data, \JSON_THROW_ON_ERROR),
            'created_at' => self::now(),
        ]);
    }
}
