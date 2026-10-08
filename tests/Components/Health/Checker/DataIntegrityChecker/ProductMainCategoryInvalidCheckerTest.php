<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\ProductMainCategoryInvalidChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Shopware\Core\Defaults;
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
    }

    public function testCountsMainCategoriesThatAreUnassignedInactiveOrOutsideTheTree(): void
    {
        $assignedCategoryId = $this->createCategory($this->navigationCategoryId, true);

        $this->setMainCategory(
            $this->createProduct(['categories' => [['id' => $assignedCategoryId]]]),
            $this->createCategory($this->navigationCategoryId, true),
        );

        $inactiveCategoryId = $this->createCategory($this->navigationCategoryId, false);
        $this->setMainCategory($this->createProduct(['categories' => [['id' => $inactiveCategoryId]]]), $inactiveCategoryId);

        $foreignCategoryId = $this->createCategory($this->createCategory(null, true), true);
        $this->setMainCategory($this->createProduct(['categories' => [['id' => $foreignCategoryId]]]), $foreignCategoryId);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 3);
    }

    #[DataProvider('hiddenCategoryVersions')]
    public function testCountsHiddenMainCategoriesOnlyWhereTheBreadcrumbIgnoresThem(string $version, int $expected): void
    {
        $hiddenCategoryId = $this->createCategory($this->navigationCategoryId, true);
        $this->connection->executeStatement('UPDATE category SET visible = 0 WHERE id = :id', ['id' => Uuid::fromHexToBytes($hiddenCategoryId)]);
        $this->setMainCategory($this->createProduct(['categories' => [['id' => $hiddenCategoryId]]]), $hiddenCategoryId);

        static::assertAffectedCount($this->collectResult(new ProductMainCategoryInvalidChecker($this->connection, $version), self::ID), $expected);
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

    private function setMainCategory(string $productId, string $categoryId): void
    {
        $this->connection->insert('main_category', [
            'id' => Uuid::randomBytes(),
            'product_id' => Uuid::fromHexToBytes($productId),
            'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'category_id' => Uuid::fromHexToBytes($categoryId),
            'category_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'sales_channel_id' => $this->salesChannelId,
            'created_at' => self::now(),
        ]);
    }
}
