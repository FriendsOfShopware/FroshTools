<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\NumberRangeTypeMissingChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(NumberRangeTypeMissingChecker::class)]
class NumberRangeTypeMissingCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'number-range-type-missing';

    private NumberRangeTypeMissingChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(NumberRangeTypeMissingChecker::class);
        $this->connection->executeStatement(
            'DELETE FROM number_range WHERE type_id NOT IN (SELECT id FROM number_range_type)'
        );
    }

    public function testReportsOkForNumberRangesWithExistingType(): void
    {
        $this->createNumberRange((string) $this->connection->fetchOne('SELECT id FROM number_range_type LIMIT 1'));

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsNumberRangesWithDeletedType(): void
    {
        $this->createNumberRange(Uuid::randomBytes());

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 1);
    }

    private function createNumberRange(string $typeId): void
    {
        $this->connection->insert('number_range', [
            'id' => Uuid::randomBytes(),
            'type_id' => $typeId,
            'global' => 1,
            'pattern' => '{n}',
            'start' => 10000,
            'created_at' => self::now(),
        ]);
    }
}
