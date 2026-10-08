<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\ProductLayoutTypeChecker;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProductLayoutTypeChecker::class)]
class ProductLayoutTypeCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'product-layout-wrong-type';

    private ProductLayoutTypeChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(ProductLayoutTypeChecker::class);
        $this->connection->executeStatement('UPDATE product SET cms_page_id = NULL, cms_page_version_id = NULL');
    }

    public function testReportsOkForProductPageLayouts(): void
    {
        $this->setLayout($this->createProduct(), $this->createCmsPage('product_detail'));

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsProductsWithOtherLayouts(): void
    {
        $this->setLayout($this->createProduct(), $this->createCmsPage('product_list'));
        $this->setLayout($this->createProduct(), $this->createCmsPage('landingpage'));

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }

    private function setLayout(string $productId, string $cmsPageId): void
    {
        $this->updateProduct($productId, 'cms_page_id', Uuid::fromHexToBytes($cmsPageId));
        $this->updateProduct($productId, 'cms_page_version_id', Uuid::fromHexToBytes(Defaults::LIVE_VERSION));
    }
}
