<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\CustomerRequestedGroupMissingChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(CustomerRequestedGroupMissingChecker::class)]
class CustomerRequestedGroupMissingCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'customer-requested-group-missing';

    private CustomerRequestedGroupMissingChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(CustomerRequestedGroupMissingChecker::class);
        $this->connection->executeStatement(
            <<<'SQL'
                UPDATE customer c
                SET c.requested_customer_group_id = NULL
                WHERE NOT EXISTS (SELECT 1 FROM customer_group g WHERE g.id = c.requested_customer_group_id)
                SQL
        );
    }

    public function testReportsOkForExistingRequestedGroups(): void
    {
        $this->createCustomer(['requested_customer_group_id' => $this->connection->fetchOne('SELECT id FROM customer_group LIMIT 1')]);
        $this->createCustomer(['requested_customer_group_id' => null]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsCustomersRequestingADeletedGroup(): void
    {
        $this->createCustomer(['requested_customer_group_id' => $this->connection->fetchOne('SELECT id FROM customer_group LIMIT 1')]);
        $this->createCustomer(['requested_customer_group_id' => Uuid::randomBytes()]);
        $this->createCustomer(['requested_customer_group_id' => Uuid::randomBytes()]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }
}
