<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\ProductDescriptionTooLongChecker;
use Frosh\Tools\Components\Health\DataIntegrityCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(ProductDescriptionTooLongChecker::class)]
class ProductDescriptionTooLongCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'product-description-too-long';

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection->executeStatement('UPDATE product_translation SET description = NULL WHERE LENGTH(description) > 32766');
    }

    public function testReportsOkWithoutLongDescriptions(): void
    {
        $this->setDescription($this->createProduct(), str_repeat('a', 32766));

        static::assertAffectedCount($this->collectResult($this->createChecker('6.6.5.0'), self::ID), 0);
    }

    public function testCountsDescriptionsAboveTheLimitInBytes(): void
    {
        $this->setDescription($this->createProduct(), str_repeat('a', 32767));
        $this->setDescription($this->createProduct(), str_repeat('ä', 16384));

        static::assertAffectedCount($this->collectResult($this->createChecker('6.6.5.0'), self::ID), 2);
    }

    #[DataProvider('fixedVersions')]
    public function testIsSkippedOnVersionsWithIgnoreAbove(string $version): void
    {
        $this->setDescription($this->createProduct(), str_repeat('a', 32767));

        $collection = new DataIntegrityCollection();
        $this->createChecker($version)->collect($collection);

        static::assertCount(0, $collection);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fixedVersions(): iterable
    {
        yield '6.6.6.0' => ['6.6.6.0'];
        yield '6.7.0.0' => ['6.7.0.0'];
    }

    private function createChecker(string $version): ProductDescriptionTooLongChecker
    {
        return new ProductDescriptionTooLongChecker($this->connection, $version);
    }

    private function setDescription(string $productId, string $description): void
    {
        $this->connection->executeStatement(
            'UPDATE product_translation SET description = :description WHERE product_id = :id AND product_version_id = :liveVersionId',
            [
                'description' => $description,
                'id' => Uuid::fromHexToBytes($productId),
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
        );
    }
}
