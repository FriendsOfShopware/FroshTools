<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\VariantDuplicateOptionsChecker;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(VariantDuplicateOptionsChecker::class)]
class VariantDuplicateOptionsCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'variant-duplicate-options';

    private VariantDuplicateOptionsChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(VariantDuplicateOptionsChecker::class);
        $this->connection->executeStatement('DELETE FROM product_option');
    }

    public function testReportsOkWhenEveryVariantHasOtherOptions(): void
    {
        [$red, $blue, $small] = $this->createPropertyOptions(3);

        $mainProductId = $this->createProduct();
        $this->createVariant($mainProductId, [$red, $small]);
        $this->createVariant($mainProductId, [$blue, $small]);
        $this->createVariant($mainProductId, [$red]);

        $this->createVariant($this->createProduct(), [$red, $small]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
        static::assertAffectedEntities($this->checker, 'product', []);
    }

    public function testDoesNotReportDifferentOptionSetsWithACommonPrefix(): void
    {
        $optionIds = $this->createPropertyOptions(3);
        sort($optionIds);
        [$first, $second, $third] = $optionIds;

        $mainProductId = $this->createProduct();
        $this->createVariant($mainProductId, [$first, $second]);
        $this->createVariant($mainProductId, [$first, $third]);

        $groupConcatMaxLength = $this->connection->fetchOne('SELECT @@SESSION.group_concat_max_len');
        $this->connection->executeStatement('SET SESSION group_concat_max_len = 4');

        try {
            $result = $this->collectResult($this->checker, self::ID);
        } finally {
            $this->connection->executeStatement('SET SESSION group_concat_max_len = ' . (int) $groupConcatMaxLength);
        }

        static::assertAffectedCount($result, 0);
        static::assertAffectedEntities($this->checker, 'product', []);
    }

    public function testCountsEveryVariantOfADuplicateOptionSet(): void
    {
        [$red, $blue, $small] = $this->createPropertyOptions(3);

        $mainProductId = $this->createProduct();
        $redSmallId = $this->createVariant($mainProductId, [$red, $small]);
        $smallRedId = $this->createVariant($mainProductId, [$small, $red]);
        $this->createVariant($mainProductId, [$blue, $small]);
        $firstBlueId = $this->createVariant($mainProductId, [$blue]);
        $secondBlueId = $this->createVariant($mainProductId, [$blue]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 4);
        static::assertAffectedEntities($this->checker, 'product', [$redSmallId, $smallRedId, $firstBlueId, $secondBlueId]);
    }

    public function testListsAVariantWithSeveralDuplicatesOnce(): void
    {
        [$red] = $this->createPropertyOptions(1);

        $mainProductId = $this->createProduct();
        $firstId = $this->createVariant($mainProductId, [$red]);
        $secondId = $this->createVariant($mainProductId, [$red]);
        $thirdId = $this->createVariant($mainProductId, [$red]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 3);
        static::assertAffectedEntities($this->checker, 'product', [$firstId, $secondId, $thirdId]);
    }

    public function testIgnoresInactiveVariants(): void
    {
        [$red, $blue] = $this->createPropertyOptions(2);

        $inactiveMainProductId = $this->createProduct(['active' => false]);
        $this->createVariant($inactiveMainProductId, [$red]);
        $this->createVariant($inactiveMainProductId, [$red]);
        $this->createVariant($inactiveMainProductId, [$blue], ['active' => true]);

        $mainProductId = $this->createProduct(['active' => true]);
        $this->createVariant($mainProductId, [$red]);
        $this->createVariant($mainProductId, [$red], ['active' => false]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
        static::assertAffectedEntities($this->checker, 'product', []);
    }

    public function testCountsActiveVariantsOfAnInactiveMainProduct(): void
    {
        [$red] = $this->createPropertyOptions(1);

        $mainProductId = $this->createProduct(['active' => false]);
        $firstId = $this->createVariant($mainProductId, [$red], ['active' => true]);
        $secondId = $this->createVariant($mainProductId, [$red], ['active' => true]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
        static::assertAffectedEntities($this->checker, 'product', [$firstId, $secondId]);
    }

    /**
     * @param list<string> $optionIds
     * @param array<string, mixed> $data
     */
    private function createVariant(string $mainProductId, array $optionIds, array $data = []): string
    {
        return $this->createProduct([
            'parentId' => $mainProductId,
            'options' => array_map(static fn (string $id) => ['id' => $id], $optionIds),
            ...$data,
        ]);
    }
}
