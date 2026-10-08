<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\ArrayParameterType;
use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\NumberRangeSalesChannelMissingChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(NumberRangeSalesChannelMissingChecker::class)]
class NumberRangeSalesChannelMissingCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'number-range-sales-channel-missing';

    private NumberRangeSalesChannelMissingChecker $checker;

    /**
     * @var list<string>
     */
    private array $salesChannelIds;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(NumberRangeSalesChannelMissingChecker::class);
        $this->connection->executeStatement('UPDATE number_range SET global = 1');
        $this->connection->executeStatement(
            'DELETE FROM number_range_type WHERE id NOT IN (SELECT type_id FROM number_range)'
        );

        /** @var list<string> $salesChannelIds */
        $salesChannelIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM sales_channel WHERE active = 1 AND type_id IN (:typeIds)',
            ['typeIds' => [Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT), Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_API)]],
            ['typeIds' => ArrayParameterType::BINARY],
        );
        if (\count($salesChannelIds) < 2) {
            static::markTestSkipped('Two active storefront or headless sales channels are required');
        }

        $this->salesChannelIds = $salesChannelIds;
    }

    public function testReportsOkForGlobalTypesGlobalRangesAssignedRangesAndInactiveSalesChannels(): void
    {
        $globalTypeId = $this->createType(true);
        $this->createNumberRange($globalTypeId, false, []);

        $typeWithGlobalRangeId = $this->createType(false);
        $this->createNumberRange($typeWithGlobalRangeId, true, []);

        $assignedTypeId = $this->createType(false);
        $this->createNumberRange($assignedTypeId, false, \array_slice($this->salesChannelIds, 0, 1));
        $this->createNumberRange($assignedTypeId, false, \array_slice($this->salesChannelIds, 1));

        $this->createType(false, false);

        $partlyAssignedTypeId = $this->createType(false);
        $this->createNumberRange($partlyAssignedTypeId, false, \array_slice($this->salesChannelIds, 0, 1));
        $this->connection->executeStatement(
            'UPDATE sales_channel SET active = 0 WHERE id IN (:ids)',
            ['ids' => \array_slice($this->salesChannelIds, 1)],
            ['ids' => ArrayParameterType::BINARY],
        );

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsActiveSalesChannelsWithoutNumberRangeForAType(): void
    {
        $partlyAssignedTypeId = $this->createType(false);
        $this->createNumberRange($partlyAssignedTypeId, false, \array_slice($this->salesChannelIds, 0, 1));

        $this->createType(false);

        $salesChannelCount = \count($this->salesChannelIds);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), ($salesChannelCount - 1) + $salesChannelCount);
    }

    private function createType(bool $global, bool $withTechnicalName = true): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('number_range_type', [
            'id' => $id,
            'technical_name' => $withTechnicalName ? 'frosh_' . Uuid::randomHex() : null,
            'global' => (int) $global,
            'created_at' => self::now(),
        ]);

        return $id;
    }

    /**
     * @param list<string> $salesChannelIds
     */
    private function createNumberRange(string $typeId, bool $global, array $salesChannelIds): void
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('number_range', [
            'id' => $id,
            'type_id' => $typeId,
            'global' => (int) $global,
            'pattern' => '{n}',
            'start' => 10000,
            'created_at' => self::now(),
        ]);

        foreach ($salesChannelIds as $salesChannelId) {
            $this->connection->insert('number_range_sales_channel', [
                'id' => Uuid::randomBytes(),
                'number_range_id' => $id,
                'sales_channel_id' => $salesChannelId,
                'number_range_type_id' => $typeId,
                'created_at' => self::now(),
            ]);
        }
    }
}
