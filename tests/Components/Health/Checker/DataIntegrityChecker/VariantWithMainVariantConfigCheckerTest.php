<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\VariantWithMainVariantConfigChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(VariantWithMainVariantConfigChecker::class)]
class VariantWithMainVariantConfigCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'variant-main-variant-config';

    private VariantWithMainVariantConfigChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(VariantWithMainVariantConfigChecker::class);
        $this->connection->executeStatement('UPDATE product SET variant_listing_config = NULL');
    }

    public function testReportsOkWhenOnlyMainProductsConfigureTheMainVariant(): void
    {
        $mainProductId = $this->createProduct();
        $variantId = $this->createProduct(['parentId' => $mainProductId]);
        $this->updateProduct($mainProductId, 'variant_listing_config', json_encode(['mainVariantId' => $variantId], \JSON_THROW_ON_ERROR));

        $this->updateProduct($variantId, 'variant_listing_config', '{"mainVariantId": null}');

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsVariantsWithAnOwnMainVariant(): void
    {
        $mainProductId = $this->createProduct();
        $variantId = $this->createProduct(['parentId' => $mainProductId]);
        $otherVariantId = $this->createProduct(['parentId' => $mainProductId]);

        $this->updateProduct($variantId, 'variant_listing_config', json_encode(['mainVariantId' => $otherVariantId], \JSON_THROW_ON_ERROR));
        $this->updateProduct($otherVariantId, 'variant_listing_config', json_encode(['mainVariantId' => Uuid::randomHex()], \JSON_THROW_ON_ERROR));

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }
}
