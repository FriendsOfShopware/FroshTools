<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\DuplicateCustomerEmailsChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(DuplicateCustomerEmailsChecker::class)]
class DuplicateCustomerEmailsCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'duplicate-customer-emails';

    private const CONFIG_KEY = 'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel';

    private DuplicateCustomerEmailsChecker $checker;

    private SystemConfigService $systemConfigService;

    private string $salesChannelId;

    private string $otherSalesChannelId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(DuplicateCustomerEmailsChecker::class);
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);

        /** @var list<string> $salesChannelIds */
        $salesChannelIds = $this->connection->fetchFirstColumn('SELECT id FROM sales_channel LIMIT 2');
        if (\count($salesChannelIds) < 2) {
            static::markTestSkipped('Two sales channels are required');
        }

        [$this->salesChannelId, $this->otherSalesChannelId] = $salesChannelIds;

        $this->connection->executeStatement('UPDATE customer SET guest = 1');
    }

    public function testReportsOkForUniqueEmailsAndGuests(): void
    {
        $this->systemConfigService->set(self::CONFIG_KEY, false);

        $this->createCustomer('unique@example.com');
        $this->createCustomer('guest@example.com');
        $this->createCustomer('guest@example.com', guest: true);
        $this->createCustomer('guest@example.com', guest: true);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testComparesEmailsCaseInsensitivelyButNotAccentInsensitively(): void
    {
        $this->systemConfigService->set(self::CONFIG_KEY, false);

        $this->createCustomer('Duplicate@example.com');
        $this->createCustomer('duplicate@EXAMPLE.com');
        $this->createCustomer('josé@example.com');
        $this->createCustomer('jose@example.com');

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }

    public function testEmailsMustBeUniqueAcrossSalesChannelsWhenCustomersAreNotBound(): void
    {
        $this->systemConfigService->set(self::CONFIG_KEY, false);

        $this->createCustomer('duplicate@example.com', $this->salesChannelId);
        $this->createCustomer('duplicate@example.com', $this->otherSalesChannelId);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }

    public function testEmailsMustBeUniquePerBoundSalesChannelWhenCustomersAreBound(): void
    {
        $this->systemConfigService->set(self::CONFIG_KEY, true);

        $this->createCustomer('bound@example.com', $this->salesChannelId);
        $this->createCustomer('bound@example.com', $this->otherSalesChannelId);

        $this->createCustomer('same@example.com', $this->salesChannelId);
        $this->createCustomer('same@example.com', $this->salesChannelId);

        $this->createCustomer('unbound@example.com', $this->salesChannelId);
        $this->createCustomer('unbound@example.com');

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 4);
    }

    private function createCustomer(string $email, ?string $boundSalesChannelId = null, bool $guest = false): void
    {
        $this->connection->insert('customer', [
            'id' => Uuid::randomBytes(),
            'customer_group_id' => $this->connection->fetchOne('SELECT id FROM customer_group LIMIT 1'),
            'sales_channel_id' => $boundSalesChannelId ?? $this->salesChannelId,
            'bound_sales_channel_id' => $boundSalesChannelId,
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'default_billing_address_id' => Uuid::randomBytes(),
            'default_shipping_address_id' => Uuid::randomBytes(),
            'customer_number' => Uuid::randomHex(),
            'first_name' => 'Frosh',
            'last_name' => 'Tools',
            'email' => $email,
            'guest' => (int) $guest,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);
    }
}
