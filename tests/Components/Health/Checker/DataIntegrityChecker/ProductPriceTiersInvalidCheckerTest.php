<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\ProductPriceTiersInvalidChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(ProductPriceTiersInvalidChecker::class)]
class ProductPriceTiersInvalidCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'product-price-tiers-invalid';

    private ProductPriceTiersInvalidChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(ProductPriceTiersInvalidChecker::class);
        $this->connection->executeStatement('DELETE FROM product_price');
    }

    public function testReportsOkForContiguousTiers(): void
    {
        $ruleId = $this->createRule();
        $otherRuleId = $this->createRule();

        $productId = $this->createProduct();
        $this->addPrice($productId, $ruleId, 1, 5);
        $this->addPrice($productId, $ruleId, 6, 10);
        $this->addPrice($productId, $ruleId, 11, null);
        $this->addPrice($productId, $otherRuleId, 1, null);

        $this->addPrice($this->createProduct(), $ruleId, 1, 10);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsProductsWithMisplacedGappedOrOverlappingTiers(): void
    {
        $ruleId = $this->createRule();
        $otherRuleId = $this->createRule();

        $this->addPrice($this->createProduct(), $ruleId, 2, null);

        $gap = $this->createProduct();
        $this->addPrice($gap, $ruleId, 1, 5);
        $this->addPrice($gap, $ruleId, 7, null);

        $overlap = $this->createProduct();
        $this->addPrice($overlap, $ruleId, 1, 10);
        $this->addPrice($overlap, $ruleId, 5, null);

        $openTierInBetween = $this->createProduct();
        $this->addPrice($openTierInBetween, $ruleId, 1, null);
        $this->addPrice($openTierInBetween, $ruleId, 5, null);

        $this->addPrice($this->createProduct(), $ruleId, 1, 0);

        $twoInvalidRules = $this->createProduct();
        $this->addPrice($twoInvalidRules, $ruleId, 3, null);
        $this->addPrice($twoInvalidRules, $otherRuleId, 1, 4);
        $this->addPrice($twoInvalidRules, $otherRuleId, 4, null);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 6);
    }

    private function createRule(): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('rule', [
            'id' => $id,
            'name' => 'Frosh data integrity check rule',
            'priority' => 1,
            'created_at' => self::now(),
        ]);

        return $id;
    }

    private function addPrice(string $productId, string $ruleId, int $quantityStart, ?int $quantityEnd): void
    {
        $this->connection->insert('product_price', [
            'id' => Uuid::randomBytes(),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'rule_id' => $ruleId,
            'product_id' => Uuid::fromHexToBytes($productId),
            'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'price' => json_encode([
                'c' . Defaults::CURRENCY => ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 10, 'linked' => false],
            ], \JSON_THROW_ON_ERROR),
            'quantity_start' => $quantityStart,
            'quantity_end' => $quantityEnd,
            'created_at' => self::now(),
        ]);
    }
}
