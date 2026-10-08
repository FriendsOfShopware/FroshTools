<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\ProductExportCurrencyInvalidChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(ProductExportCurrencyInvalidChecker::class)]
class ProductExportCurrencyInvalidCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'product-export-currency-invalid';

    private string $salesChannelId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection->executeStatement('DELETE FROM product_export');

        $salesChannelId = $this->connection->fetchOne('SELECT id FROM sales_channel LIMIT 1');
        static::assertIsString($salesChannelId);
        $this->salesChannelId = $salesChannelId;

        $this->connection->executeStatement(
            'INSERT IGNORE INTO sales_channel_currency (sales_channel_id, currency_id) VALUES (:salesChannelId, :currencyId)',
            ['salesChannelId' => $salesChannelId, 'currencyId' => Uuid::fromHexToBytes(Defaults::CURRENCY)],
        );
    }

    public function testReportsOkForExportsWithAnAssignedCurrency(): void
    {
        $this->createExport(Uuid::fromHexToBytes(Defaults::CURRENCY));

        static::assertAffectedCount($this->collectResult($this->createChecker('6.7.15.0'), self::ID), 0);
    }

    public function testCountsExportsWithAMissingOrUnassignedCurrency(): void
    {
        $this->createExport(Uuid::fromHexToBytes(Defaults::CURRENCY));
        $this->createExport($this->createCurrency());
        $this->createExport(Uuid::randomBytes());

        static::assertAffectedCount($this->collectResult($this->createChecker('6.7.15.0'), self::ID), 2);
    }

    public function testCountsOnlyMissingCurrenciesBefore6_7_15(): void
    {
        $this->createExport($this->createCurrency());
        $this->createExport(Uuid::randomBytes());

        static::assertAffectedCount($this->collectResult($this->createChecker('6.7.14.1'), self::ID), 1);
    }

    private function createChecker(string $version): ProductExportCurrencyInvalidChecker
    {
        return new ProductExportCurrencyInvalidChecker($this->connection, $version);
    }

    private function createExport(string $currencyId): void
    {
        $streamId = Uuid::randomBytes();
        $this->connection->insert('product_stream', [
            'id' => $streamId,
            'created_at' => self::now(),
        ]);

        $this->connection->insert('product_export', [
            'id' => Uuid::randomBytes(),
            'product_stream_id' => $streamId,
            'storefront_sales_channel_id' => $this->salesChannelId,
            'sales_channel_id' => $this->salesChannelId,
            'file_name' => Uuid::randomHex() . '.csv',
            'access_key' => Uuid::randomHex(),
            'encoding' => 'UTF-8',
            'file_format' => 'csv',
            '`interval`' => 0,
            'currency_id' => $currencyId,
            'created_at' => self::now(),
        ]);
    }

    private function createCurrency(): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('currency', [
            'id' => $id,
            'iso_code' => 'XTS',
            'factor' => 1,
            'symbol' => 'X',
            'item_rounding' => '{"decimals": 2, "interval": 0.01, "roundForNet": true}',
            'total_rounding' => '{"decimals": 2, "interval": 0.01, "roundForNet": true}',
            'created_at' => self::now(),
        ]);

        return $id;
    }
}
