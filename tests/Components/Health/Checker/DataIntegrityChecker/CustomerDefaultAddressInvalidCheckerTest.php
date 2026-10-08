<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\CustomerDefaultAddressInvalidChecker;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CustomerDefaultAddressInvalidChecker::class)]
class CustomerDefaultAddressInvalidCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'customer-default-address-invalid';

    private CustomerDefaultAddressInvalidChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(CustomerDefaultAddressInvalidChecker::class);
        $this->connection->executeStatement('UPDATE customer SET default_billing_address_id = id, default_shipping_address_id = id');
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT IGNORE INTO customer_address (id, customer_id, country_id, first_name, last_name, street, city, created_at)
                SELECT c.id, c.id, (SELECT id FROM country LIMIT 1), 'Frosh', 'Tools', 'Street', 'City', NOW(3)
                FROM customer c
                SQL
        );
    }

    public function testReportsOkForOwnDefaultAddresses(): void
    {
        $customerId = $this->createCustomer();
        $addressId = $this->createAddress($customerId);
        $this->setDefaultAddresses($customerId, $addressId, $addressId);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsMissingDefaultAddressesAndAddressesOfOtherCustomers(): void
    {
        $customerId = $this->createCustomer();
        $addressId = $this->createAddress($customerId);
        $this->setDefaultAddresses($customerId, $addressId, $addressId);

        $this->setDefaultAddresses($this->createCustomer(), $this->createAddress($customerId), Uuid::randomBytes());

        $otherCustomerId = $this->createCustomer();
        $this->setDefaultAddresses($otherCustomerId, $addressId, $this->createAddress($otherCustomerId));

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }

    private function createCustomer(): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('customer', [
            'id' => $id,
            'customer_group_id' => $this->connection->fetchOne('SELECT id FROM customer_group LIMIT 1'),
            'sales_channel_id' => $this->connection->fetchOne('SELECT id FROM sales_channel LIMIT 1'),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'default_billing_address_id' => Uuid::randomBytes(),
            'default_shipping_address_id' => Uuid::randomBytes(),
            'customer_number' => Uuid::randomHex(),
            'first_name' => 'Frosh',
            'last_name' => 'Tools',
            'email' => Uuid::randomHex() . '@example.com',
            'created_at' => self::now(),
        ]);

        return $id;
    }

    private function createAddress(string $customerId): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('customer_address', [
            'id' => $id,
            'customer_id' => $customerId,
            'country_id' => $this->connection->fetchOne('SELECT id FROM country LIMIT 1'),
            'first_name' => 'Frosh',
            'last_name' => 'Tools',
            'street' => 'Street',
            'city' => 'City',
            'created_at' => self::now(),
        ]);

        return $id;
    }

    private function setDefaultAddresses(string $customerId, string $billingAddressId, string $shippingAddressId): void
    {
        $this->connection->update(
            'customer',
            ['default_billing_address_id' => $billingAddressId, 'default_shipping_address_id' => $shippingAddressId],
            ['id' => $customerId],
        );
    }
}
