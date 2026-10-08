<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\CanonicalFromOtherProductChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(CanonicalFromOtherProductChecker::class)]
class CanonicalFromOtherProductCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'product-canonical-other-family';

    private CanonicalFromOtherProductChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(CanonicalFromOtherProductChecker::class);
        $this->connection->executeStatement('UPDATE product SET canonical_product_id = NULL, canonical_product_version_id = NULL');
    }

    public function testReportsOkWhenCanonicalProductsBelongToTheSameProduct(): void
    {
        $mainProductId = $this->createProduct();
        $variantId = $this->createProduct(['parentId' => $mainProductId]);
        $this->setCanonical($mainProductId, $variantId);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsProductsWithACanonicalProductOfAnotherProduct(): void
    {
        $mainProductId = $this->createProduct();
        $variantId = $this->createProduct(['parentId' => $mainProductId]);

        $otherMainProductId = $this->createProduct();
        $otherVariantId = $this->createProduct(['parentId' => $otherMainProductId]);

        $this->setCanonical($mainProductId, $otherVariantId);
        $this->setCanonical($variantId, $otherMainProductId);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }

    private function setCanonical(string $productId, string $canonicalProductId): void
    {
        $this->updateProduct($productId, 'canonical_product_id', Uuid::fromHexToBytes($canonicalProductId));
        $this->updateProduct($productId, 'canonical_product_version_id', Uuid::fromHexToBytes(Defaults::LIVE_VERSION));
    }
}
