<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\DuplicateCustomerEmailsChecker;
use PHPUnit\Framework\Attributes\CoversClass;
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

        $this->createCustomerWithEmail('unique@example.com');
        $this->createCustomerWithEmail('guest@example.com');
        $this->createCustomerWithEmail('guest@example.com', guest: true);
        $this->createCustomerWithEmail('guest@example.com', guest: true);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
        static::assertAffectedEntities($this->checker, 'customer', []);
    }

    public function testComparesEmailsCaseInsensitivelyButNotAccentInsensitively(): void
    {
        $this->systemConfigService->set(self::CONFIG_KEY, false);

        $first = $this->createCustomerWithEmail('Duplicate@example.com');
        $second = $this->createCustomerWithEmail('duplicate@EXAMPLE.com');
        $this->createCustomerWithEmail('josé@example.com');
        $this->createCustomerWithEmail('jose@example.com');

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
        static::assertAffectedEntities($this->checker, 'customer', [$first, $second]);
    }

    public function testEmailsMustBeUniqueAcrossSalesChannelsWhenCustomersAreNotBound(): void
    {
        $this->systemConfigService->set(self::CONFIG_KEY, false);

        $first = $this->createCustomerWithEmail('duplicate@example.com', $this->salesChannelId);
        $second = $this->createCustomerWithEmail('duplicate@example.com', $this->otherSalesChannelId);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
        static::assertAffectedEntities($this->checker, 'customer', [$first, $second]);
    }

    public function testEmailsMustBeUniquePerBoundSalesChannelWhenCustomersAreBound(): void
    {
        $this->systemConfigService->set(self::CONFIG_KEY, true);

        $this->createCustomerWithEmail('bound@example.com', $this->salesChannelId);
        $this->createCustomerWithEmail('bound@example.com', $this->otherSalesChannelId);

        $sameFirst = $this->createCustomerWithEmail('same@example.com', $this->salesChannelId);
        $sameSecond = $this->createCustomerWithEmail('same@example.com', $this->salesChannelId);

        $boundCustomer = $this->createCustomerWithEmail('unbound@example.com', $this->salesChannelId);
        $unboundCustomer = $this->createCustomerWithEmail('unbound@example.com');

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 4);
        static::assertAffectedEntities($this->checker, 'customer', [$sameFirst, $sameSecond, $boundCustomer, $unboundCustomer]);
    }

    private function createCustomerWithEmail(string $email, ?string $boundSalesChannelId = null, bool $guest = false): string
    {
        return Uuid::fromBytesToHex($this->createCustomer([
            'sales_channel_id' => $boundSalesChannelId ?? $this->salesChannelId,
            'bound_sales_channel_id' => $boundSalesChannelId,
            'email' => $email,
            'guest' => (int) $guest,
        ]));
    }
}
