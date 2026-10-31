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
        static::assertAffectedEntities($this->checker, 'sales_channel', []);
    }

    public function testCountsActiveSalesChannelsWithoutNumberRangeForAType(): void
    {
        $partlyAssignedTypeId = $this->createType(false);
        $this->createNumberRange($partlyAssignedTypeId, false, \array_slice($this->salesChannelIds, 0, 1));

        $this->createType(false);

        $salesChannelCount = \count($this->salesChannelIds);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), ($salesChannelCount - 1) + $salesChannelCount);
        static::assertAffectedEntities($this->checker, 'sales_channel', array_map(Uuid::fromBytesToHex(...), $this->salesChannelIds));
    }

    public function testListsEverySalesChannelMissingSeveralTypesOnce(): void
    {
        foreach ([$this->createType(false), $this->createType(false)] as $typeId) {
            $this->createNumberRange($typeId, false, \array_slice($this->salesChannelIds, 0, 1));
        }

        $missingSalesChannelIds = \array_slice($this->salesChannelIds, 1);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2 * \count($missingSalesChannelIds));
        static::assertAffectedEntities($this->checker, 'sales_channel', array_map(Uuid::fromBytesToHex(...), $missingSalesChannelIds));
    }

    public function testIgnoresTypesWhoseNumberRangesAreAssignedToNoSalesChannel(): void
    {
        $typeId = $this->createType(false);
        $this->createNumberRange($typeId, false, []);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
        static::assertAffectedEntities($this->checker, 'sales_channel', []);
    }

    public function testCountsTypesTheCoreLooksUpByNameEvenIfTheirNumberRangesAreAssignedToNoSalesChannel(): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                UPDATE number_range nr
                INNER JOIN number_range_type nrt ON nrt.id = nr.type_id
                SET nr.global = 0, nrt.global = 0
                WHERE nrt.technical_name = 'order'
                SQL
        );
        $this->connection->executeStatement(
            'DELETE nrsc FROM number_range_sales_channel nrsc INNER JOIN number_range_type nrt ON nrt.id = nrsc.number_range_type_id WHERE nrt.technical_name = \'order\''
        );

        $documentTypeName = 'frosh_' . Uuid::randomHex();
        $this->connection->insert('document_type', [
            'id' => Uuid::randomBytes(),
            'technical_name' => $documentTypeName,
            'created_at' => self::now(),
        ]);
        $documentTypeId = $this->createType(false, technicalName: 'document_' . $documentTypeName);
        $this->createNumberRange($documentTypeId, false, []);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2 * \count($this->salesChannelIds));
        static::assertAffectedEntities($this->checker, 'sales_channel', array_map(Uuid::fromBytesToHex(...), $this->salesChannelIds));
    }

    private function createType(bool $global, bool $withTechnicalName = true, ?string $technicalName = null): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('number_range_type', [
            'id' => $id,
            'technical_name' => $withTechnicalName ? ($technicalName ?? 'frosh_' . Uuid::randomHex()) : null,
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
