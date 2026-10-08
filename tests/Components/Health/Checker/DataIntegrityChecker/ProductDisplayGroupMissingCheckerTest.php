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
        $this->connection->executeStatement('UPDATE product SET display_group = SHA2(HEX(id), 256) WHERE display_group IS NULL');
    }

    public function testReportsOkForIndexedProductsAndMainProductsWithVariants(): void
    {
        $this->createProduct();

        $mainProductId = $this->createProduct();
        $this->createProduct(['parentId' => $mainProductId]);
        $this->updateProduct($mainProductId, 'display_group', null);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsProductsAndVariantsWithoutDisplayGroup(): void
    {
        $this->updateProduct($this->createProduct(), 'display_group', null);

        $mainProductId = $this->createProduct();
        $variantId = $this->createProduct(['parentId' => $mainProductId]);
        $this->createProduct(['parentId' => $mainProductId]);
        $this->updateProduct($variantId, 'display_group', null);
        $this->updateProduct($mainProductId, 'display_group', null);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }
}
