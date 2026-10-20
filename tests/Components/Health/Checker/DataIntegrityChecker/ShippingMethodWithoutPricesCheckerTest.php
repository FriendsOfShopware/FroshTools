<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\ShippingMethodWithoutPricesChecker;
use Shopware\Core\Framework\Uuid\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ShippingMethodWithoutPricesChecker::class)]
class ShippingMethodWithoutPricesCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'shipping-method-without-prices';

    private ShippingMethodWithoutPricesChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(ShippingMethodWithoutPricesChecker::class);
        $this->connection->executeStatement(
            'UPDATE shipping_method sm SET active = 0 WHERE NOT EXISTS (SELECT 1 FROM shipping_method_price price WHERE price.shipping_method_id = sm.id)'
        );
    }

    public function testReportsOkForShippingMethodsWithPricesAndInactiveOnes(): void
    {
        $shippingMethodId = $this->createShippingMethod(true);
        $this->connection->insert('shipping_method_price', [
            'id' => Uuid::randomBytes(),
            'shipping_method_id' => $shippingMethodId,
            'calculation' => 1,
            'currency_price' => '{"cb7d2554b0ce847cd82f3ac9bd1c0dfca": {"currencyId": "b7d2554b0ce847cd82f3ac9bd1c0dfca", "net": 5, "gross": 5, "linked": false}}',
            'quantity_start' => 1,
            'created_at' => self::now(),
        ]);

        $this->createShippingMethod(false);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsActiveShippingMethodsWithoutPrices(): void
    {
        $this->createShippingMethod(true);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 1);
    }

    private function createShippingMethod(bool $active): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('shipping_method', [
            'id' => $id,
            'technical_name' => 'frosh_data_integrity_check_' . Uuid::randomHex(),
            'active' => (int) $active,
            'delivery_time_id' => $this->connection->fetchOne('SELECT id FROM delivery_time LIMIT 1'),
            'created_at' => self::now(),
        ]);

        return $id;
    }
}
