<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class CustomerDefaultAddressInvalidChecker implements DataIntegrityCheckerInterface, CheckerInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $count = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM customer c
                LEFT JOIN customer_address billing
                    ON billing.id = c.default_billing_address_id
                    AND billing.customer_id = c.id
                LEFT JOIN customer_address shipping
                    ON shipping.id = c.default_shipping_address_id
                    AND shipping.customer_id = c.id
                WHERE billing.id IS NULL
                   OR shipping.id IS NULL
                SQL,
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'customer-default-address-invalid',
            'Customers with a missing default address or the address of another customer',
            $count,
        ));
    }
}
