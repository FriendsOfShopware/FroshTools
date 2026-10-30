<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\ProductWithoutVisibleCategoryChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(ProductWithoutVisibleCategoryChecker::class)]
class ProductWithoutVisibleCategoryCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'product-without-visible-category';

    private ProductWithoutVisibleCategoryChecker $checker;

    private string $salesChannelId;

    private string $navigationCategoryId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(ProductWithoutVisibleCategoryChecker::class);
        $this->connection->executeStatement('DELETE FROM product_visibility');

        $salesChannel = $this->connection->fetchAssociative('SELECT LOWER(HEX(id)) AS id, LOWER(HEX(navigation_category_id)) AS navigation_category_id FROM sales_channel LIMIT 1');
        static::assertIsArray($salesChannel);
        $this->salesChannelId = $salesChannel['id'];
        $this->navigationCategoryId = $salesChannel['navigation_category_id'];
    }

    public function testReportsOkForVisibleProductsInAnActiveAndVisibleCategoryOfTheSalesChannel(): void
    {
        $categoryId = $this->createCategory($this->navigationCategoryId, true, true);
        $this->createVisibleProduct(['categories' => [['id' => $categoryId]]]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
        static::assertAffectedEntities($this->checker, 'product', []);
    }

    public function testCountsVisibleProductsWithoutAnActiveAndVisibleCategoryOfTheSalesChannel(): void
    {
        $withoutCategoryId = $this->createVisibleProduct();
        $inactiveCategoryProductId = $this->createVisibleProduct([
            'categories' => [['id' => $this->createCategory($this->navigationCategoryId, false, true)]],
        ]);
        $hiddenCategoryProductId = $this->createVisibleProduct([
            'categories' => [['id' => $this->createCategory($this->navigationCategoryId, true, false)]],
        ]);
        $foreignCategoryProductId = $this->createVisibleProduct([
            'categories' => [['id' => $this->createCategory($this->createCategory(null, true, true), true, true)]],
        ]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 4);
        static::assertAffectedEntities($this->checker, 'product', [
            $withoutCategoryId,
            $inactiveCategoryProductId,
            $hiddenCategoryProductId,
            $foreignCategoryProductId,
        ]);
    }

    public function testIgnoresInactiveProductsAndProductsHiddenInListings(): void
    {
        $this->createVisibleProduct(['active' => false]);
        $this->createProduct([
            'active' => true,
            'visibilities' => [[
                'salesChannelId' => $this->salesChannelId,
                'visibility' => ProductVisibilityDefinition::VISIBILITY_SEARCH,
            ]],
        ]);
        $this->createProduct(['active' => true]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
        static::assertAffectedEntities($this->checker, 'product', []);
    }

    public function testIgnoresVisibilitiesInInactiveSalesChannels(): void
    {
        $this->createVisibleProduct([
            'categories' => [['id' => $this->createCategory($this->createCategory(null, true, true), true, true)]],
        ]);
        $this->connection->executeStatement(
            'UPDATE sales_channel SET active = 0 WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($this->salesChannelId)],
        );

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
        static::assertAffectedEntities($this->checker, 'product', []);
    }

    public function testReportsActiveVariantsAsTheProductHoldingTheirCategories(): void
    {
        $parentId = $this->createVisibleProduct(['active' => false]);
        $this->createProduct(['parentId' => $parentId, 'active' => true]);
        $this->createProduct(['parentId' => $parentId, 'active' => true]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 1);
        static::assertAffectedEntities($this->checker, 'product', [$parentId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createVisibleProduct(array $data = []): string
    {
        return $this->createProduct(array_replace([
            'active' => true,
            'visibilities' => [[
                'salesChannelId' => $this->salesChannelId,
                'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
            ]],
        ], $data));
    }

    private function createCategory(?string $parentId, bool $active, bool $visible): string
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
            'visible' => $visible ? 1 : 0,
            'path' => $parentId === null ? null : '|' . $parentId . '|',
            'level' => $parentId === null ? 1 : 2,
            'created_at' => self::now(),
        ]);

        return $id;
    }
}
