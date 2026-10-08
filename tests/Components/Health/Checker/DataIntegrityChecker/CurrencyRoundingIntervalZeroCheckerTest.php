<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\CurrencyRoundingIntervalZeroChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(CurrencyRoundingIntervalZeroChecker::class)]
class CurrencyRoundingIntervalZeroCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'currency-rounding-interval-zero';

    private const VALID = '{"decimals": 2, "interval": 0.01, "roundForNet": true}';

    private CurrencyRoundingIntervalZeroChecker $checker;

    private int $currencyCount = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(CurrencyRoundingIntervalZeroChecker::class);
        $this->connection->executeStatement(
            'UPDATE currency SET item_rounding = :valid, total_rounding = :valid',
            ['valid' => self::VALID],
        );
        $this->connection->executeStatement('DELETE FROM currency_country_rounding');
    }

    public function testReportsOkForPositiveIntervalsAndZeroIntervalsWithMoreThanTwoDecimals(): void
    {
        $currencyId = $this->createCurrency('{"decimals": 3, "interval": 0, "roundForNet": true}', self::VALID);
        $this->createCountryRounding($currencyId, self::VALID, '{"decimals": 2, "interval": 0.05, "roundForNet": true}');

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsCurrenciesAndCountryRoundingsWithZeroInterval(): void
    {
        $this->createCurrency('{"decimals": 2, "interval": 0, "roundForNet": true}', self::VALID);
        $this->createCurrency('{"decimals": 2, "interval": 0.0, "roundForNet": true}', '{"decimals": 1, "interval": 0, "roundForNet": true}');
        $currencyId = $this->createCurrency(self::VALID, self::VALID);
        $this->createCountryRounding($currencyId, self::VALID, '{"decimals": 2, "roundForNet": true}');

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 3);
    }

    private function createCurrency(string $itemRounding, string $totalRounding): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('currency', [
            'id' => $id,
            'iso_code' => \sprintf('Q%02d', ++$this->currencyCount),
            'factor' => 1,
            'symbol' => 'X',
            'item_rounding' => $itemRounding,
            'total_rounding' => $totalRounding,
            'created_at' => self::now(),
        ]);

        return $id;
    }

    private function createCountryRounding(string $currencyId, string $itemRounding, string $totalRounding): void
    {
        $this->connection->insert('currency_country_rounding', [
            'id' => Uuid::randomBytes(),
            'currency_id' => $currencyId,
            'country_id' => $this->connection->fetchOne('SELECT id FROM country LIMIT 1'),
            'item_rounding' => $itemRounding,
            'total_rounding' => $totalRounding,
            'created_at' => self::now(),
        ]);
    }
}
