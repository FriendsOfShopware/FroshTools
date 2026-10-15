<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\ProductMaxPurchaseBelowMinPurchaseChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(ProductMaxPurchaseBelowMinPurchaseChecker::class)]
class ProductMaxPurchaseBelowMinPurchaseCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'product-max-purchase-below-min-purchase';

    private ProductMaxPurchaseBelowMinPurchaseChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(ProductMaxPurchaseBelowMinPurchaseChecker::class);
        $this->connection->executeStatement('UPDATE product SET max_purchase = NULL, min_purchase = 1, purchase_steps = 1');
        static::getContainer()->get(SystemConfigService::class)->set('core.cart.maxQuantity', 100);
    }

    public function testReportsOkWhenMaxPurchaseIsNotBelowMinPurchase(): void
    {
        $productId = $this->createProduct();
        $this->updateProduct($productId, 'min_purchase', '2');
        $this->updateProduct($productId, 'max_purchase', '2');

        $this->updateProduct($this->createProduct(), 'max_purchase', '5');

        $mainProductId = $this->createProduct();
        $this->updateProduct($mainProductId, 'min_purchase', '10');
        $this->updateProduct($mainProductId, 'max_purchase', '5');
        $variantId = $this->createProduct(['parentId' => $mainProductId]);
        $this->updateProduct($variantId, 'min_purchase', '1');

        $this->updateProduct($this->createProduct(), 'max_purchase', '0');

        $hiddenProductId = $this->createProduct();
        $this->updateProduct($hiddenProductId, 'min_purchase', '5');
        $this->updateProduct($hiddenProductId, 'max_purchase', '3');
        $this->updateProduct($hiddenProductId, 'purchase_steps', '5');

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsBuyableProductsWithMaxPurchaseBelowMinPurchase(): void
    {
        $productId = $this->createProduct();
        $this->updateProduct($productId, 'min_purchase', '5');
        $this->updateProduct($productId, 'max_purchase', '3');

        $this->updateProduct($this->createProduct(), 'min_purchase', '150');

        $mainProductId = $this->createProduct();
        $this->updateProduct($mainProductId, 'min_purchase', '10');
        $this->updateProduct($mainProductId, 'max_purchase', '5');
        $this->createProduct(['parentId' => $mainProductId]);
        $this->createProduct(['parentId' => $mainProductId]);
        $this->updateProduct($this->createProduct(['parentId' => $mainProductId]), 'max_purchase', '20');

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 4);
    }
}
