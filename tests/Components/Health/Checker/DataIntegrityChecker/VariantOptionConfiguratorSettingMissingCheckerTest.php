<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\VariantOptionConfiguratorSettingMissingChecker;
use Frosh\Tools\Components\Health\DataIntegrityCollection;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(VariantOptionConfiguratorSettingMissingChecker::class)]
class VariantOptionConfiguratorSettingMissingCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'variant-option-configurator-setting-missing';

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection->executeStatement('DELETE FROM product_option');
    }

    public function testReportsOkWhenAllVariantOptionsHaveASetting(): void
    {
        [$firstOptionId, $secondOptionId] = $this->createPropertyOptions(2);

        $mainProductId = $this->createProduct([
            'configuratorSettings' => [['optionId' => $firstOptionId], ['optionId' => $secondOptionId]],
        ]);
        $this->createProduct(['parentId' => $mainProductId, 'options' => [['id' => $firstOptionId]]]);
        $this->createProduct(['parentId' => $mainProductId, 'options' => [['id' => $secondOptionId]]]);

        $this->createProduct(['parentId' => $this->createProduct(), 'options' => [['id' => $firstOptionId]]]);

        static::assertAffectedCount($this->collectResult($this->createChecker('6.7.13.0'), self::ID), 0);
    }

    public function testCountsVariantsWithOptionsMissingInTheSettings(): void
    {
        [$firstOptionId, $secondOptionId, $thirdOptionId] = $this->createPropertyOptions(3);

        $mainProductId = $this->createProduct(['configuratorSettings' => [['optionId' => $firstOptionId]]]);
        $this->createProduct(['parentId' => $mainProductId, 'options' => [['id' => $firstOptionId]]]);
        $this->createProduct(['parentId' => $mainProductId, 'options' => [['id' => $secondOptionId]]]);
        $this->createProduct(['parentId' => $mainProductId, 'options' => [['id' => $firstOptionId], ['id' => $thirdOptionId]]]);

        static::assertAffectedCount($this->collectResult($this->createChecker('6.6.10.0'), self::ID), 2);
    }

    public function testIsSkippedOnVersionsThatShowOptionsWithoutSetting(): void
    {
        [$firstOptionId, $secondOptionId] = $this->createPropertyOptions(2);

        $mainProductId = $this->createProduct(['configuratorSettings' => [['optionId' => $firstOptionId]]]);
        $this->createProduct(['parentId' => $mainProductId, 'options' => [['id' => $secondOptionId]]]);

        $collection = new DataIntegrityCollection();
        $this->createChecker('6.7.14.0')->collect($collection);

        static::assertCount(0, $collection);
    }

    private function createChecker(string $version): VariantOptionConfiguratorSettingMissingChecker
    {
        return new VariantOptionConfiguratorSettingMissingChecker($this->connection, $version);
    }
}
