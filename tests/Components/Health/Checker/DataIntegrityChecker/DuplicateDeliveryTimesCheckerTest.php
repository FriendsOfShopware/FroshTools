<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\DuplicateDeliveryTimesChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(DuplicateDeliveryTimesChecker::class)]
class DuplicateDeliveryTimesCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'duplicate-delivery-times';

    private DuplicateDeliveryTimesChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(DuplicateDeliveryTimesChecker::class);
        $this->connection->executeStatement('UPDATE delivery_time SET unit = HEX(id)');
    }

    public function testReportsOkWithoutDuplicates(): void
    {
        $this->createDeliveryTime(1, 3, 'day');
        $this->createDeliveryTime(1, 3, 'week');
        $this->createDeliveryTime(2, 3, 'day');

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsEveryDeliveryTimeOfADuplicateRange(): void
    {
        $this->createDeliveryTime(1, 3, 'day');
        $this->createDeliveryTime(1, 3, 'day');
        $this->createDeliveryTime(1, 3, 'day');
        $this->createDeliveryTime(2, 4, 'week');
        $this->createDeliveryTime(2, 4, 'week');
        $this->createDeliveryTime(2, 4, 'day');

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 5);
    }

    private function createDeliveryTime(int $min, int $max, string $unit): void
    {
        $this->connection->insert('delivery_time', [
            'id' => Uuid::randomBytes(),
            'min' => $min,
            'max' => $max,
            'unit' => $unit,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);
    }
}
