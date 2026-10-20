<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\SalesChannelDomainCurrencyChecker;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SalesChannelDomainCurrencyChecker::class)]
class SalesChannelDomainCurrencyCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'sales-channel-domain-currency-unassigned';

    private SalesChannelDomainCurrencyChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(SalesChannelDomainCurrencyChecker::class);
        $this->connection->executeStatement(
            'INSERT IGNORE INTO sales_channel_currency (sales_channel_id, currency_id) SELECT sales_channel_id, currency_id FROM sales_channel_domain'
        );
    }

    public function testReportsOkWhenDomainCurrenciesAreAssigned(): void
    {
        $this->createDomain(Uuid::fromHexToBytes(Defaults::CURRENCY));
        $this->connection->executeStatement(
            'INSERT IGNORE INTO sales_channel_currency (sales_channel_id, currency_id) SELECT sales_channel_id, currency_id FROM sales_channel_domain'
        );

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsDomainsWithAnUnassignedCurrency(): void
    {
        $currencyId = Uuid::randomBytes();
        $this->connection->insert('currency', [
            'id' => $currencyId,
            'iso_code' => 'XTS',
            'factor' => 1,
            'symbol' => 'X',
            'item_rounding' => '{"decimals": 2, "interval": 0.01, "roundForNet": true}',
            'total_rounding' => '{"decimals": 2, "interval": 0.01, "roundForNet": true}',
            'created_at' => self::now(),
        ]);

        $this->createDomain($currencyId);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 1);
    }

    private function createDomain(string $currencyId): void
    {
        $this->connection->insert('sales_channel_domain', [
            'id' => Uuid::randomBytes(),
            'sales_channel_id' => $this->connection->fetchOne('SELECT id FROM sales_channel LIMIT 1'),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'url' => 'https://frosh-data-integrity-check-' . Uuid::randomHex() . '.test',
            'currency_id' => $currencyId,
            'snippet_set_id' => $this->connection->fetchOne('SELECT id FROM snippet_set LIMIT 1'),
            'created_at' => self::now(),
        ]);
    }
}
