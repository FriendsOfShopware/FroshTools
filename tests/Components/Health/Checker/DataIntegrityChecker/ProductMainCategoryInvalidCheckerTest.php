<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\ProductMainCategoryInvalidChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(ProductMainCategoryInvalidChecker::class)]
class ProductMainCategoryInvalidCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'product-main-category-invalid';

    private ProductMainCategoryInvalidChecker $checker;

    private string $salesChannelId;

    private string $navigationCategoryId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(ProductMainCategoryInvalidChecker::class);
        $this->connection->executeStatement('DELETE FROM main_category');

        $salesChannel = $this->connection->fetchAssociative('SELECT id, navigation_category_id FROM sales_channel LIMIT 1');
        static::assertIsArray($salesChannel);
        $this->salesChannelId = $salesChannel['id'];
        $this->navigationCategoryId = Uuid::fromBytesToHex($salesChannel['navigation_category_id']);
    }

    public function testReportsOkForAssignedActiveCategoriesInTheSalesChannelTree(): void
    {
        $categoryId = $this->createCategory($this->navigationCategoryId, true);
        $productId = $this->createProduct(['categories' => [['id' => $categoryId]]]);
        $this->setMainCategory($productId, $categoryId);

        $variantId = $this->createProduct(['parentId' => $productId]);
        $this->setMainCategory($variantId, $categoryId);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
        static::assertAffectedEntities($this->checker, 'product', []);
    }

    public function testCountsMainCategoriesThatAreUnassignedInactiveOrOutsideTheTree(): void
    {
        $assignedCategoryId = $this->createCategory($this->navigationCategoryId, true);

        $unassignedProductId = $this->createProduct(['categories' => [['id' => $assignedCategoryId]]]);
        $this->setMainCategory($unassignedProductId, $this->createCategory($this->navigationCategoryId, true));

        $inactiveCategoryId = $this->createCategory($this->navigationCategoryId, false);
        $inactiveProductId = $this->createProduct(['categories' => [['id' => $inactiveCategoryId]]]);
        $this->setMainCategory($inactiveProductId, $inactiveCategoryId);

        $foreignCategoryId = $this->createCategory($this->createCategory(null, true), true);
        $foreignProductId = $this->createProduct(['categories' => [['id' => $foreignCategoryId]]]);
        $this->setMainCategory($foreignProductId, $foreignCategoryId);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 3);
        static::assertAffectedEntities($this->checker, 'product', [$unassignedProductId, $inactiveProductId, $foreignProductId]);
    }

    public function testListsAProductWithInvalidMainCategoriesInSeveralSalesChannelsOnce(): void
    {
        $inactiveCategoryId = $this->createCategory($this->navigationCategoryId, false);
        $productId = $this->createProduct(['categories' => [['id' => $inactiveCategoryId]]]);
        $this->setMainCategory($productId, $inactiveCategoryId);
        $this->setMainCategory($productId, $inactiveCategoryId, $this->createSalesChannel());

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
        static::assertAffectedEntities($this->checker, 'product', [$productId]);
    }

    #[DataProvider('hiddenCategoryVersions')]
    public function testCountsHiddenMainCategoriesOnlyWhereTheBreadcrumbIgnoresThem(string $version, int $expected): void
    {
        $hiddenCategoryId = $this->createCategory($this->navigationCategoryId, true);
        $this->connection->executeStatement('UPDATE category SET visible = 0 WHERE id = :id', ['id' => Uuid::fromHexToBytes($hiddenCategoryId)]);
        $productId = $this->createProduct(['categories' => [['id' => $hiddenCategoryId]]]);
        $this->setMainCategory($productId, $hiddenCategoryId);

        $checker = new ProductMainCategoryInvalidChecker($this->connection, $version);
        static::assertAffectedCount($this->collectResult($checker, self::ID), $expected);
        static::assertAffectedEntities($checker, 'product', $expected === 0 ? [] : [$productId]);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function hiddenCategoryVersions(): iterable
    {
        yield '6.7.8.0' => ['6.7.8.0', 0];
        yield '6.7.9.0' => ['6.7.9.0', 1];
        yield '6.7.14.2' => ['6.7.14.2', 1];
        yield '6.7.15.0' => ['6.7.15.0', 0];
    }

    private function createCategory(?string $parentId, bool $active): string
    {
        $id = Uuid::randomHex();
        $liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $this->connection->insert('category', [
            'id' => Uuid::fromHexToBytes($id),
            'version_id' => $liveVersionId,
            'parent_id' => $parentId === null ? null : Uuid::fromHexToBytes($parentId),
            'parent_version_id' => $parentId === null ? null : $liveVersionId,
            'type' => 'page',
            'active' => $active ? 1 : 0,
            'path' => $parentId === null ? null : '|' . $parentId . '|',
            'level' => $parentId === null ? 1 : 2,
            'created_at' => self::now(),
        ]);

        return $id;
    }

    private function createSalesChannel(): string
    {
        $id = Uuid::randomHex();

        /** @var EntityRepository $repository */
        $repository = static::getContainer()->get('sales_channel.repository');
        $repository->create([[
            'id' => $id,
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'name' => 'Frosh data integrity check sales channel',
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'currencies' => [['id' => Defaults::CURRENCY]],
            'customerGroupId' => $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM customer_group LIMIT 1'),
            'paymentMethodId' => $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM payment_method LIMIT 1'),
            'shippingMethodId' => $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM shipping_method LIMIT 1'),
            'countryId' => $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM country LIMIT 1'),
            'navigationCategoryId' => $this->navigationCategoryId,
        ]], Context::createDefaultContext());

        return Uuid::fromHexToBytes($id);
    }

    private function setMainCategory(string $productId, string $categoryId, ?string $salesChannelId = null): void
    {
        $this->connection->insert('main_category', [
            'id' => Uuid::randomBytes(),
            'product_id' => Uuid::fromHexToBytes($productId),
            'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'category_id' => Uuid::fromHexToBytes($categoryId),
            'category_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'sales_channel_id' => $salesChannelId ?? $this->salesChannelId,
            'created_at' => self::now(),
        ]);
    }
}
