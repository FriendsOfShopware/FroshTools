<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\CategoryLayoutTypeChecker;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CategoryLayoutTypeChecker::class)]
class CategoryLayoutTypeCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'category-layout-wrong-type';

    private CategoryLayoutTypeChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(CategoryLayoutTypeChecker::class);
        $this->connection->executeStatement('UPDATE category SET cms_page_id = NULL');
    }

    public function testReportsOkForCategoryLayouts(): void
    {
        $this->createCategory($this->createCmsPage('product_list'));
        $this->createCategory($this->createCmsPage('landingpage'));
        $this->createCategory($this->createCmsPage('page'));

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsCategoriesWithAProductPageLayout(): void
    {
        $this->createCategory($this->createCmsPage('product_detail'));

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 1);
    }

    private function createCategory(string $cmsPageId): void
    {
        $this->connection->insert('category', [
            'id' => Uuid::randomBytes(),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'type' => 'page',
            'cms_page_id' => Uuid::fromHexToBytes($cmsPageId),
            'cms_page_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'created_at' => self::now(),
        ]);
    }
}
