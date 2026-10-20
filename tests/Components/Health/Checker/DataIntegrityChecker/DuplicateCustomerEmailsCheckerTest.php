<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\DuplicateCustomerEmailsChecker;
use PHPUnit\Framework\Attributes\CoversClass;
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

        $this->createCustomerWithEmail('unique@example.com');
        $this->createCustomerWithEmail('guest@example.com');
        $this->createCustomerWithEmail('guest@example.com', guest: true);
        $this->createCustomerWithEmail('guest@example.com', guest: true);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testComparesEmailsCaseInsensitivelyButNotAccentInsensitively(): void
    {
        $this->systemConfigService->set(self::CONFIG_KEY, false);

        $this->createCustomerWithEmail('Duplicate@example.com');
        $this->createCustomerWithEmail('duplicate@EXAMPLE.com');
        $this->createCustomerWithEmail('josé@example.com');
        $this->createCustomerWithEmail('jose@example.com');

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }

    public function testEmailsMustBeUniqueAcrossSalesChannelsWhenCustomersAreNotBound(): void
    {
        $this->systemConfigService->set(self::CONFIG_KEY, false);

        $this->createCustomerWithEmail('duplicate@example.com', $this->salesChannelId);
        $this->createCustomerWithEmail('duplicate@example.com', $this->otherSalesChannelId);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }

    public function testEmailsMustBeUniquePerBoundSalesChannelWhenCustomersAreBound(): void
    {
        $this->systemConfigService->set(self::CONFIG_KEY, true);

        $this->createCustomerWithEmail('bound@example.com', $this->salesChannelId);
        $this->createCustomerWithEmail('bound@example.com', $this->otherSalesChannelId);

        $this->createCustomerWithEmail('same@example.com', $this->salesChannelId);
        $this->createCustomerWithEmail('same@example.com', $this->salesChannelId);

        $this->createCustomerWithEmail('unbound@example.com', $this->salesChannelId);
        $this->createCustomerWithEmail('unbound@example.com');

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 4);
    }

    private function createCustomerWithEmail(string $email, ?string $boundSalesChannelId = null, bool $guest = false): void
    {
        $this->createCustomer([
            'sales_channel_id' => $boundSalesChannelId ?? $this->salesChannelId,
            'bound_sales_channel_id' => $boundSalesChannelId,
            'email' => $email,
            'guest' => (int) $guest,
        ]);
    }
}
