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
    }

    public function testCountsEveryVariantOfADuplicateOptionSet(): void
    {
        [$red, $blue, $small] = $this->createPropertyOptions(3);

        $mainProductId = $this->createProduct();
        $this->createVariant($mainProductId, [$red, $small]);
        $this->createVariant($mainProductId, [$small, $red]);
        $this->createVariant($mainProductId, [$blue, $small]);
        $this->createVariant($mainProductId, [$blue]);
        $this->createVariant($mainProductId, [$blue]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 4);
    }

    /**
     * @param list<string> $optionIds
     */
    private function createVariant(string $mainProductId, array $optionIds): void
    {
        $this->createProduct([
            'parentId' => $mainProductId,
            'options' => array_map(static fn (string $id) => ['id' => $id], $optionIds),
        ]);
    }
}
