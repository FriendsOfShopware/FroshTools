<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\CanonicalOnProductWithoutVariantsChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(CanonicalOnProductWithoutVariantsChecker::class)]
class CanonicalOnProductWithoutVariantsCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'product-canonical-without-variants';

    private CanonicalOnProductWithoutVariantsChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(CanonicalOnProductWithoutVariantsChecker::class);
        $this->connection->executeStatement('UPDATE product SET canonical_product_id = NULL, canonical_product_version_id = NULL');
    }

    public function testReportsOkWithoutCanonicalProducts(): void
    {
        $this->createProduct();

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsMainProductsWithoutVariantsThatHaveACanonicalProduct(): void
    {
        $otherProductId = $this->createProduct();

        $simpleProductId = $this->createProduct();
        $this->updateProduct($simpleProductId, 'canonical_product_id', Uuid::fromHexToBytes($otherProductId));

        $mainProductId = $this->createProduct();
        $variantId = $this->createProduct(['parentId' => $mainProductId]);
        $this->updateProduct($mainProductId, 'canonical_product_id', Uuid::fromHexToBytes($variantId));

        $this->updateProduct($variantId, 'canonical_product_id', Uuid::fromHexToBytes($variantId));

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 1);
    }
}
