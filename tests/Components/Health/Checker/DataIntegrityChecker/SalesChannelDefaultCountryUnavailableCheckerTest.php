<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\SalesChannelDefaultCountryUnavailableChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(SalesChannelDefaultCountryUnavailableChecker::class)]
class SalesChannelDefaultCountryUnavailableCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'sales-channel-default-country-unavailable';

    private SalesChannelDefaultCountryUnavailableChecker $checker;

    private string $storefrontSalesChannelId;

    private string $headlessSalesChannelId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(SalesChannelDefaultCountryUnavailableChecker::class);

        $storefrontSalesChannelId = $this->fetchSalesChannelId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        $headlessSalesChannelId = $this->fetchSalesChannelId(Defaults::SALES_CHANNEL_TYPE_API);
        if ($storefrontSalesChannelId === null || $headlessSalesChannelId === null) {
            static::markTestSkipped('A storefront and a headless sales channel are required');
        }

        $this->storefrontSalesChannelId = $storefrontSalesChannelId;
        $this->headlessSalesChannelId = $headlessSalesChannelId;

        $this->connection->executeStatement(
            'INSERT IGNORE INTO sales_channel_country (sales_channel_id, country_id) SELECT id, country_id FROM sales_channel'
        );
        $this->connection->executeStatement(
            'UPDATE country SET active = 1, shipping_available = 1 WHERE id IN (SELECT country_id FROM sales_channel)'
        );
    }

    public function testReportsOkForAvailableDefaultCountriesAndInactiveSalesChannels(): void
    {
        $this->setDefaultCountry($this->storefrontSalesChannelId, $this->createCountry(true, true), true);

        $this->connection->executeStatement(
            'UPDATE sales_channel SET active = 0 WHERE id = :id',
            ['id' => $this->headlessSalesChannelId],
        );
        $this->setDefaultCountry($this->headlessSalesChannelId, $this->createCountry(false, false), false);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsSalesChannelsWithUnassignedInactiveOrNonShippingDefaultCountry(): void
    {
        $this->setDefaultCountry($this->storefrontSalesChannelId, $this->createCountry(true, true), false);
        $this->setDefaultCountry($this->headlessSalesChannelId, $this->createCountry(true, false), true);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }

    public function testCountsSalesChannelWithInactiveDefaultCountry(): void
    {
        $this->setDefaultCountry($this->storefrontSalesChannelId, $this->createCountry(false, true), true);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 1);
    }

    private function fetchSalesChannelId(string $typeId): ?string
    {
        $id = $this->connection->fetchOne(
            'SELECT id FROM sales_channel WHERE type_id = :typeId AND active = 1 LIMIT 1',
            ['typeId' => Uuid::fromHexToBytes($typeId)],
        );

        return \is_string($id) ? $id : null;
    }

    private function createCountry(bool $active, bool $shippingAvailable): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('country', [
            'id' => $id,
            'iso' => 'XX',
            'active' => (int) $active,
            'shipping_available' => (int) $shippingAvailable,
            'created_at' => self::now(),
        ]);

        return $id;
    }

    private function setDefaultCountry(string $salesChannelId, string $countryId, bool $assigned): void
    {
        $this->connection->executeStatement(
            'UPDATE sales_channel SET country_id = :countryId WHERE id = :id',
            ['countryId' => $countryId, 'id' => $salesChannelId],
        );

        if ($assigned) {
            $this->connection->insert('sales_channel_country', [
                'sales_channel_id' => $salesChannelId,
                'country_id' => $countryId,
            ]);
        }
    }
}
