<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\PromotionDiscountSetGroupMissingChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(PromotionDiscountSetGroupMissingChecker::class)]
class PromotionDiscountSetGroupMissingCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'promotion-discount-setgroup-missing';

    private PromotionDiscountSetGroupMissingChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(PromotionDiscountSetGroupMissingChecker::class);
        $this->connection->executeStatement('UPDATE promotion_discount SET scope = :scope WHERE scope LIKE :setGroupScope', [
            'scope' => 'cart',
            'setGroupScope' => 'setgroup-%',
        ]);
    }

    public function testReportsOkForExistingSetGroupsAndInactivePromotions(): void
    {
        $promotionId = $this->createPromotion(true, 2);
        $this->createDiscount($promotionId, 'setgroup-1');
        $this->createDiscount($promotionId, 'setgroup-2');
        $this->createDiscount($promotionId, 'cart');

        $inactivePromotionId = $this->createPromotion(false, 0);
        $this->createDiscount($inactivePromotionId, 'setgroup-3');

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsDiscountsOfActivePromotionsReferencingMissingSetGroups(): void
    {
        $promotionId = $this->createPromotion(true, 1);
        $this->createDiscount($promotionId, 'setgroup-1');
        $this->createDiscount($promotionId, 'setgroup-2');
        $this->createDiscount($promotionId, 'setgroup-0');
        $this->createDiscount($promotionId, 'setgroup-01');

        $withoutGroupsId = $this->createPromotion(true, 0);
        $this->createDiscount($withoutGroupsId, 'setgroup-1');

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 4);
    }

    private function createPromotion(bool $active, int $setGroupCount): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('promotion', [
            'id' => $id,
            'active' => (int) $active,
            'use_setgroups' => (int) ($setGroupCount > 0),
            'created_at' => self::now(),
        ]);

        for ($i = 0; $i < $setGroupCount; ++$i) {
            $this->connection->insert('promotion_setgroup', [
                'id' => Uuid::randomBytes(),
                'promotion_id' => $id,
                'packager_key' => 'COUNT',
                'sorter_key' => 'PRICE_ASC',
                'value' => 1,
                'created_at' => self::now(),
            ]);
        }

        return $id;
    }

    private function createDiscount(string $promotionId, string $scope): void
    {
        $this->connection->insert('promotion_discount', [
            'id' => Uuid::randomBytes(),
            'promotion_id' => $promotionId,
            'scope' => $scope,
            'type' => 'percentage',
            'value' => 10,
            'created_at' => self::now(),
        ]);
    }
}
