<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\ProductDeliveryTimeMissingChecker;
use Shopware\Core\Framework\Uuid\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProductDeliveryTimeMissingChecker::class)]
class ProductDeliveryTimeMissingCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'product-delivery-time-missing';

    private ProductDeliveryTimeMissingChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(ProductDeliveryTimeMissingChecker::class);
        $this->connection->executeStatement('UPDATE product SET delivery_time_id = NULL');
    }

    public function testReportsOkForExistingDeliveryTimes(): void
    {
        $deliveryTimeId = $this->connection->fetchOne('SELECT id FROM delivery_time LIMIT 1');
        static::assertIsString($deliveryTimeId);

        $this->updateProduct($this->createProduct(), 'delivery_time_id', $deliveryTimeId);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsProductsWithADeletedDeliveryTime(): void
    {
        $this->updateProduct($this->createProduct(), 'delivery_time_id', Uuid::randomBytes());

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 1);
    }
}
