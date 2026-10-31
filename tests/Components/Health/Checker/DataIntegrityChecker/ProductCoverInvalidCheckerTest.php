<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\ProductCoverInvalidChecker;
use Shopware\Core\Framework\Uuid\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProductCoverInvalidChecker::class)]
class ProductCoverInvalidCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'product-cover-invalid';

    private ProductCoverInvalidChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(ProductCoverInvalidChecker::class);
        $this->connection->executeStatement('UPDATE product SET product_media_id = NULL, product_media_version_id = NULL');
    }

    public function testReportsOkForOwnCoversAndCoversOfTheMainProduct(): void
    {
        $mainProductId = $this->createProductWithCover();
        $variantId = $this->createProduct(['parentId' => $mainProductId]);

        $this->updateProduct($variantId, 'product_media_id', $this->coverOf($mainProductId));

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
        static::assertAffectedEntities($this->checker, 'product', []);
    }

    public function testCountsMissingCoversAndCoversOfOtherProducts(): void
    {
        $otherProductId = $this->createProductWithCover();

        $foreignCoverId = $this->createProduct();
        $this->updateProduct($foreignCoverId, 'product_media_id', $this->coverOf($otherProductId));
        $missingCoverId = $this->createProduct();
        $this->updateProduct($missingCoverId, 'product_media_id', Uuid::randomBytes());

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
        static::assertAffectedEntities($this->checker, 'product', [$foreignCoverId, $missingCoverId]);
    }

    public function testReportsOkForCoversOfOtherProductsWithTheSameImageAsAnOwnImage(): void
    {
        $mediaId = Uuid::randomHex();
        $otherProductId = $this->createProductWithCover($mediaId);

        $productId = $this->createProduct(['media' => [['mediaId' => $mediaId]]]);
        $this->updateProduct($productId, 'product_media_id', $this->coverOf($otherProductId));

        $mainProductId = $this->createProduct(['media' => [['mediaId' => $mediaId]]]);
        $variantId = $this->createProduct(['parentId' => $mainProductId]);
        $this->updateProduct($variantId, 'product_media_id', $this->coverOf($otherProductId));

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
        static::assertAffectedEntities($this->checker, 'product', []);
    }

    public function testCountsCoversWithTheSameImageOnceTheProductMediaOfTheOtherProductIsDeleted(): void
    {
        $mediaId = Uuid::randomHex();
        $deletedProductId = $this->createProductWithCover($mediaId);
        $otherProductId = $this->createProductWithCover($mediaId);

        $productId = $this->createProduct(['media' => [['mediaId' => $mediaId]]]);
        $this->updateProduct($productId, 'product_media_id', $this->coverOf($deletedProductId));
        $otherCoverProductId = $this->createProduct(['media' => [['mediaId' => $mediaId]]]);
        $this->updateProduct($otherCoverProductId, 'product_media_id', $this->coverOf($otherProductId));

        $this->connection->executeStatement('DELETE FROM product WHERE id = :id', ['id' => Uuid::fromHexToBytes($deletedProductId)]);
        $this->connection->executeStatement('DELETE FROM product_media WHERE id = :id', ['id' => $this->coverOf($otherProductId)]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 3);
        static::assertAffectedEntities($this->checker, 'product', [$productId, $otherCoverProductId, $otherProductId]);
    }

    private function createProductWithCover(?string $mediaId = null): string
    {
        $productMediaId = Uuid::randomHex();

        return $this->createProduct([
            'media' => [['id' => $productMediaId, 'media' => ['id' => $mediaId ?? Uuid::randomHex()]]],
            'coverId' => $productMediaId,
        ]);
    }

    private function coverOf(string $productId): string
    {
        $coverId = $this->connection->fetchOne(
            'SELECT product_media_id FROM product WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($productId)],
        );
        static::assertIsString($coverId);

        return $coverId;
    }
}
