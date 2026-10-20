<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\SalesChannelNavigationCategoryInactiveChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(SalesChannelNavigationCategoryInactiveChecker::class)]
class SalesChannelNavigationCategoryInactiveCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'sales-channel-navigation-category-inactive';

    private SalesChannelNavigationCategoryInactiveChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(SalesChannelNavigationCategoryInactiveChecker::class);
        $this->connection->executeStatement(
            'UPDATE category INNER JOIN sales_channel ON sales_channel.navigation_category_id = category.id SET category.active = 1'
        );
    }

    public function testReportsOkWhenNavigationCategoriesAreActive(): void
    {
        $this->createSalesChannel(Defaults::SALES_CHANNEL_TYPE_STOREFRONT, true, true);
        $this->createSalesChannel(Defaults::SALES_CHANNEL_TYPE_STOREFRONT, false, false);
        $this->createSalesChannel(Defaults::SALES_CHANNEL_TYPE_API, true, false);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsActiveStorefrontsWithAnInactiveNavigationCategory(): void
    {
        $this->createSalesChannel(Defaults::SALES_CHANNEL_TYPE_STOREFRONT, true, false);
        $this->createSalesChannel(Defaults::SALES_CHANNEL_TYPE_STOREFRONT, true, false);
        $this->createSalesChannel(Defaults::SALES_CHANNEL_TYPE_STOREFRONT, false, false);
        $this->createSalesChannel(Defaults::SALES_CHANNEL_TYPE_API, true, false);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }

    private function createSalesChannel(string $typeId, bool $active, bool $categoryActive): void
    {
        $categoryId = Uuid::randomHex();

        $this->connection->insert('category', [
            'id' => Uuid::fromHexToBytes($categoryId),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'type' => 'page',
            'active' => $categoryActive ? 1 : 0,
            'created_at' => self::now(),
        ]);

        /** @var EntityRepository $repository */
        $repository = static::getContainer()->get('sales_channel.repository');
        $repository->create([[
            'id' => Uuid::randomHex(),
            'typeId' => $typeId,
            'name' => 'Frosh data integrity check sales channel',
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'active' => $active,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'currencies' => [['id' => Defaults::CURRENCY]],
            'customerGroupId' => $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM customer_group LIMIT 1'),
            'paymentMethodId' => $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM payment_method LIMIT 1'),
            'shippingMethodId' => $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM shipping_method LIMIT 1'),
            'countryId' => $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM country LIMIT 1'),
            'navigationCategoryId' => $categoryId,
        ]], Context::createDefaultContext());
    }
}
