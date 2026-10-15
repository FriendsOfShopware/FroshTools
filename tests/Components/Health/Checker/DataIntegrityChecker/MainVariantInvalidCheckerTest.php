<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\MainVariantInvalidChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(MainVariantInvalidChecker::class)]
class MainVariantInvalidCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'product-main-variant-invalid';

    private MainVariantInvalidChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(MainVariantInvalidChecker::class);
        $this->connection->executeStatement('UPDATE product SET variant_listing_config = NULL');
    }

    public function testReportsOkWhenTheMainVariantIsOneOfTheOwnVariants(): void
    {
        $mainProductId = $this->createProduct();
        $variantId = $this->createProduct(['parentId' => $mainProductId]);
        $this->setMainVariant($mainProductId, $variantId);

        $otherMainProductId = $this->createProduct();
        $this->updateProduct($otherMainProductId, 'variant_listing_config', '{"mainVariantId": null, "displayParent": true}');

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsMissingAndForeignMainVariants(): void
    {
        $mainProductId = $this->createProduct();
        $this->createProduct(['parentId' => $mainProductId]);

        $otherMainProductId = $this->createProduct();
        $otherVariantId = $this->createProduct(['parentId' => $otherMainProductId]);

        $this->setMainVariant($mainProductId, $otherVariantId);

        $this->setMainVariant($otherMainProductId, $mainProductId);

        $this->setMainVariant($this->createProduct(), Uuid::randomHex());

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 3);
    }

    private function setMainVariant(string $productId, string $mainVariantId): void
    {
        $this->updateProduct($productId, 'variant_listing_config', json_encode(['mainVariantId' => $mainVariantId], \JSON_THROW_ON_ERROR));
    }
}
