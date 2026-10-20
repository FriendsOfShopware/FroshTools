<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\VariantWithoutOptionsChecker;
use Shopware\Core\Framework\Uuid\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(VariantWithoutOptionsChecker::class)]
class VariantWithoutOptionsCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'variant-without-options';

    private VariantWithoutOptionsChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(VariantWithoutOptionsChecker::class);

        [$optionId] = $this->createPropertyOptions(1);
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO product_option (product_id, product_version_id, property_group_option_id)
                SELECT p.id, p.version_id, :optionId
                FROM product p
                WHERE p.parent_id IS NOT NULL
                  AND NOT EXISTS (SELECT 1 FROM product_option po WHERE po.product_id = p.id AND po.product_version_id = p.version_id)
                SQL,
            ['optionId' => Uuid::fromHexToBytes($optionId)],
        );
    }

    public function testReportsOkWhenEveryVariantHasOptions(): void
    {
        [$optionId] = $this->createPropertyOptions(1);
        $this->createProduct(['parentId' => $this->createProduct(), 'options' => [['id' => $optionId]]]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsVariantsWithoutOptions(): void
    {
        $mainProductId = $this->createProduct();
        $this->createProduct(['parentId' => $mainProductId]);
        $this->createProduct(['parentId' => $mainProductId]);

        $this->createProduct();

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }
}
