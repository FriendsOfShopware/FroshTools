<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\CategorySortingInvalidChecker;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CategorySortingInvalidChecker::class)]
class CategorySortingInvalidCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'category-sorting-invalid';

    private CategorySortingInvalidChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(CategorySortingInvalidChecker::class);
        $this->connection->executeStatement('UPDATE category SET after_category_id = NULL, after_category_version_id = NULL');
    }

    public function testReportsOkWhenCategoriesAreSortedAfterSiblings(): void
    {
        $parentId = $this->createCategory();
        $firstId = $this->createCategory($parentId);
        $this->createCategory($parentId, $firstId);

        $this->createCategory(null, $this->createCategory());

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsCategoriesSortedAfterACategoryOfAnotherParent(): void
    {
        $parentId = $this->createCategory();
        $childId = $this->createCategory($parentId);

        $this->createCategory($this->createCategory(), $childId);
        $this->createCategory(null, $childId);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }

    private function createCategory(?string $parentId = null, ?string $afterCategoryId = null): string
    {
        $id = Uuid::randomBytes();
        $liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $this->connection->insert('category', [
            'id' => $id,
            'version_id' => $liveVersionId,
            'parent_id' => $parentId,
            'parent_version_id' => $parentId === null ? null : $liveVersionId,
            'after_category_id' => $afterCategoryId,
            'after_category_version_id' => $afterCategoryId === null ? null : $liveVersionId,
            'type' => 'page',
            'created_at' => self::now(),
        ]);

        return $id;
    }
}
