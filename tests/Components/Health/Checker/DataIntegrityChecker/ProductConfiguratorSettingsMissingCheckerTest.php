<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\ProductConfiguratorSettingsMissingChecker;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProductConfiguratorSettingsMissingChecker::class)]
class ProductConfiguratorSettingsMissingCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'product-configurator-settings-missing';

    private ProductConfiguratorSettingsMissingChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(ProductConfiguratorSettingsMissingChecker::class);
        $this->connection->executeStatement('DELETE FROM product_option');
    }

    public function testReportsOkForMainProductsWithConfiguratorSettings(): void
    {
        [$firstOptionId, $secondOptionId] = $this->createPropertyOptions(2);

        $mainProductId = $this->createProduct([
            'configuratorSettings' => [['optionId' => $firstOptionId], ['optionId' => $secondOptionId]],
        ]);
        $this->createProduct(['parentId' => $mainProductId, 'options' => [['id' => $firstOptionId]]]);
        $this->createProduct(['parentId' => $mainProductId, 'options' => [['id' => $secondOptionId]]]);

        $this->createProduct(['parentId' => $this->createProduct()]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsMainProductsWithVariantOptionsButNoConfiguratorSettings(): void
    {
        [$firstOptionId, $secondOptionId] = $this->createPropertyOptions(2);

        $firstMainProductId = $this->createProduct();
        $this->createProduct(['parentId' => $firstMainProductId, 'options' => [['id' => $firstOptionId]]]);
        $this->createProduct(['parentId' => $firstMainProductId, 'options' => [['id' => $secondOptionId]]]);

        $secondMainProductId = $this->createProduct();
        $this->createProduct(['parentId' => $secondMainProductId, 'options' => [['id' => $firstOptionId]]]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }
}
