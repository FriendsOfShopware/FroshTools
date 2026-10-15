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
    }

    public function testCountsMissingCoversAndCoversOfOtherProducts(): void
    {
        $otherProductId = $this->createProductWithCover();

        $this->updateProduct($this->createProduct(), 'product_media_id', $this->coverOf($otherProductId));
        $this->updateProduct($this->createProduct(), 'product_media_id', Uuid::randomBytes());

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }

    private function createProductWithCover(): string
    {
        $productMediaId = Uuid::randomHex();

        return $this->createProduct([
            'media' => [['id' => $productMediaId, 'media' => ['id' => Uuid::randomHex()]]],
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
