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
        static::assertAffectedEntities($this->createChecker('6.6.5.0'), 'product', []);
    }

    public function testCountsDescriptionsAboveTheLimitInBytes(): void
    {
        $asciiProductId = $this->createProduct();
        $this->setDescription($asciiProductId, str_repeat('a', 32767));
        $multibyteProductId = $this->createProduct();
        $this->setDescription($multibyteProductId, str_repeat('ä', 16384));

        static::assertAffectedCount($this->collectResult($this->createChecker('6.6.5.0'), self::ID), 2);
        static::assertAffectedEntities($this->createChecker('6.6.5.0'), 'product', [$asciiProductId, $multibyteProductId]);
    }

    public function testListsAProductWithLongDescriptionsInSeveralLanguagesOnce(): void
    {
        $productId = $this->createProduct();
        $this->setDescription($productId, str_repeat('a', 32767));
        $this->addTranslation($productId, $this->createLanguage(), str_repeat('b', 32767));

        static::assertAffectedCount($this->collectResult($this->createChecker('6.6.5.0'), self::ID), 2);
        static::assertAffectedEntities($this->createChecker('6.6.5.0'), 'product', [$productId]);
    }

    #[DataProvider('fixedVersions')]
    public function testIsSkippedOnVersionsWithIgnoreAbove(string $version): void
    {
        $this->setDescription($this->createProduct(), str_repeat('a', 32767));

        $collection = new DataIntegrityCollection();
        $this->createChecker($version)->collect($collection);

        static::assertCount(0, $collection);
        static::assertAffectedEntities($this->createChecker($version), 'product', []);
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

    private function createLanguage(): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('language', [
            'id' => $id,
            'name' => 'Frosh data integrity check language',
            'locale_id' => $this->connection->fetchOne('SELECT id FROM locale LIMIT 1'),
            'created_at' => self::now(),
        ]);

        return $id;
    }

    private function addTranslation(string $productId, string $languageId, string $description): void
    {
        $this->connection->insert('product_translation', [
            'product_id' => Uuid::fromHexToBytes($productId),
            'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'language_id' => $languageId,
            'description' => $description,
            'created_at' => self::now(),
        ]);
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
