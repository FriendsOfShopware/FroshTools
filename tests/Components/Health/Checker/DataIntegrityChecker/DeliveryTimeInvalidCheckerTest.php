<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\DeliveryTimeInvalidChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(DeliveryTimeInvalidChecker::class)]
class DeliveryTimeInvalidCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'delivery-time-invalid';

    private DeliveryTimeInvalidChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(DeliveryTimeInvalidChecker::class);
        $this->connection->executeStatement(
            'UPDATE delivery_time SET unit = :unit, `min` = 1, `max` = 2 WHERE CAST(unit AS BINARY) NOT IN (\'hour\', \'day\', \'week\', \'month\', \'year\') OR `min` < 0 OR `max` < 0',
            ['unit' => 'day'],
        );
    }

    public function testReportsOkForSupportedUnits(): void
    {
        foreach (['hour', 'day', 'week', 'month', 'year'] as $unit) {
            $this->createDeliveryTime($unit, 0, 3);
        }

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsUnsupportedUnitsAndNegativeDurations(): void
    {
        $this->createDeliveryTime('Day', 1, 3);
        $this->createDeliveryTime('days', 1, 3);
        $this->createDeliveryTime('week', -1, 2);
        $this->createDeliveryTime('day', 1, -2);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 4);
    }

    private function createDeliveryTime(string $unit, int $min, int $max): void
    {
        $this->connection->insert('delivery_time', [
            'id' => Uuid::randomBytes(),
            'unit' => $unit,
            'min' => $min,
            'max' => $max,
            'created_at' => self::now(),
        ]);
    }
}
