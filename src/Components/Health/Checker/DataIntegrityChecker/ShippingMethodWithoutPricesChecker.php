<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class ShippingMethodWithoutPricesChecker implements DataIntegrityCheckerInterface, CheckerInterface
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
                FROM shipping_method sm
                WHERE sm.active = 1
                  AND NOT EXISTS (SELECT 1
                                  FROM shipping_method_price price
                                  WHERE price.shipping_method_id = sm.id)
                SQL,
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'shipping-method-without-prices',
            'Active shipping methods without prices',
            $count,
        ));
    }
}
