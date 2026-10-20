<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\ProductDisplayGroupMissingChecker;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProductDisplayGroupMissingChecker::class)]
class ProductDisplayGroupMissingCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'product-display-group-missing';

    private ProductDisplayGroupMissingChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(ProductDisplayGroupMissingChecker::class);
        $this->fillDisplayGroups();
    }

    public function testReportsOkForIndexedProductsAndMainProductsWithVariants(): void
    {
        $this->createProduct();

        $mainProductId = $this->createProduct();
        $this->createProduct(['parentId' => $mainProductId]);
        $this->fillDisplayGroups();
        $this->updateProduct($mainProductId, 'display_group', null);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsProductsAndVariantsWithoutDisplayGroup(): void
    {
        $productId = $this->createProduct();

        $mainProductId = $this->createProduct();
        $variantId = $this->createProduct(['parentId' => $mainProductId]);
        $this->createProduct(['parentId' => $mainProductId]);
        $this->fillDisplayGroups();
        $this->updateProduct($productId, 'display_group', null);
        $this->updateProduct($variantId, 'display_group', null);
        $this->updateProduct($mainProductId, 'display_group', null);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }

    private function fillDisplayGroups(): void
    {
        $this->connection->executeStatement('UPDATE product SET display_group = MD5(HEX(id)) WHERE display_group IS NULL');
    }
}
