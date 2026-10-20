<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\CurrencyFactorInvalidChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(CurrencyFactorInvalidChecker::class)]
class CurrencyFactorInvalidCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'currency-factor-invalid';

    private CurrencyFactorInvalidChecker $checker;

    private int $currencyCount = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(CurrencyFactorInvalidChecker::class);
        $this->connection->executeStatement('UPDATE currency SET factor = 1 WHERE factor <= 0');
        $this->setDefaultCurrencyFactor(1.0);
    }

    public function testReportsOkForPositiveFactorsAndDefaultCurrencyFactorOne(): void
    {
        $this->createCurrency(0.5);
        $this->createCurrency(150.0);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsNonPositiveFactorsAndDefaultCurrencyFactorOtherThanOne(): void
    {
        $this->createCurrency(0.0);
        $this->createCurrency(-1.0);
        $this->setDefaultCurrencyFactor(1.2);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 3);
    }

    private function setDefaultCurrencyFactor(float $factor): void
    {
        $this->connection->executeStatement(
            'UPDATE currency SET factor = :factor WHERE id = :id',
            ['factor' => $factor, 'id' => Uuid::fromHexToBytes(Defaults::CURRENCY)],
        );
    }

    private function createCurrency(float $factor): void
    {
        $this->connection->insert('currency', [
            'id' => Uuid::randomBytes(),
            'iso_code' => \sprintf('Q%02d', ++$this->currencyCount),
            'factor' => $factor,
            'symbol' => 'X',
            'item_rounding' => '{"decimals": 2, "interval": 0.01, "roundForNet": true}',
            'total_rounding' => '{"decimals": 2, "interval": 0.01, "roundForNet": true}',
            'created_at' => self::now(),
        ]);
    }
}
