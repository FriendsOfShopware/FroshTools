<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\ProductExportCurrencyInvalidChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
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
        static::assertAffectedEntities($this->createChecker('6.7.15.0'), 'sales_channel', []);
    }

    public function testCountsExportsWithAMissingOrUnassignedCurrency(): void
    {
        $this->createExport(Uuid::fromHexToBytes(Defaults::CURRENCY));
        $this->createExport($this->createCurrency());
        $this->createExport(Uuid::randomBytes());

        static::assertAffectedCount($this->collectResult($this->createChecker('6.7.15.0'), self::ID), 2);
        static::assertAffectedEntities($this->createChecker('6.7.15.0'), 'sales_channel', [Uuid::fromBytesToHex($this->salesChannelId)]);
    }

    public function testCountsOnlyMissingCurrenciesBefore6_7_15(): void
    {
        $this->createExport($this->createCurrency());
        $this->createExport(Uuid::randomBytes());

        static::assertAffectedCount($this->collectResult($this->createChecker('6.7.14.1'), self::ID), 1);
        static::assertAffectedEntities($this->createChecker('6.7.14.1'), 'sales_channel', [Uuid::fromBytesToHex($this->salesChannelId)]);
    }

    public function testListsEveryComparisonSalesChannelWithAnInvalidExportOnce(): void
    {
        $otherSalesChannelId = $this->createComparisonSalesChannel();

        $this->createExport(Uuid::randomBytes());
        $this->createExport($this->createCurrency());
        $this->createExport(Uuid::randomBytes(), $otherSalesChannelId);
        $this->createExport(Uuid::fromHexToBytes(Defaults::CURRENCY), $this->createComparisonSalesChannel());

        static::assertAffectedCount($this->collectResult($this->createChecker('6.7.15.0'), self::ID), 3);
        static::assertAffectedEntities($this->createChecker('6.7.15.0'), 'sales_channel', [
            Uuid::fromBytesToHex($this->salesChannelId),
            Uuid::fromBytesToHex($otherSalesChannelId),
        ]);
    }

    private function createChecker(string $version): ProductExportCurrencyInvalidChecker
    {
        return new ProductExportCurrencyInvalidChecker($this->connection, $version);
    }

    private function createExport(string $currencyId, ?string $salesChannelId = null): void
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
            'sales_channel_id' => $salesChannelId ?? $this->salesChannelId,
            'file_name' => Uuid::randomHex() . '.csv',
            'access_key' => Uuid::randomHex(),
            'encoding' => 'UTF-8',
            'file_format' => 'csv',
            '`interval`' => 0,
            'currency_id' => $currencyId,
            'created_at' => self::now(),
        ]);
    }

    private function createComparisonSalesChannel(): string
    {
        $id = Uuid::randomHex();

        /** @var EntityRepository $repository */
        $repository = static::getContainer()->get('sales_channel.repository');
        $repository->create([[
            'id' => $id,
            'typeId' => Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON,
            'name' => 'Frosh data integrity check product comparison',
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'currencies' => [['id' => Defaults::CURRENCY]],
            'customerGroupId' => $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM customer_group LIMIT 1'),
            'paymentMethodId' => $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM payment_method LIMIT 1'),
            'shippingMethodId' => $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM shipping_method LIMIT 1'),
            'countryId' => $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM country LIMIT 1'),
            'navigationCategoryId' => $this->connection->fetchOne('SELECT LOWER(HEX(navigation_category_id)) FROM sales_channel WHERE id = :id', ['id' => $this->salesChannelId]),
        ]], Context::createDefaultContext());

        return Uuid::fromHexToBytes($id);
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
