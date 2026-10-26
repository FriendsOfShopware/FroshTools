<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;

class CustomerDefaultAddressInvalidChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'customer-default-address-invalid';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT c.id
        FROM customer c
        LEFT JOIN customer_address billing
            ON billing.id = c.default_billing_address_id
            AND billing.customer_id = c.id
        LEFT JOIN customer_address shipping
            ON shipping.id = c.default_shipping_address_id
            AND shipping.customer_id = c.id
        WHERE billing.id IS NULL
           OR shipping.id IS NULL
        SQL;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function getAffectedEntity(): string
    {
        return CustomerDefinition::ENTITY_NAME;
    }

    public function collect(HealthCollection $collection): void
    {
        $count = AffectedEntities::countRows($this->connection, self::AFFECTED_QUERY);

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Customers with a missing default address or the address of another customer',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery($this->connection, CustomerDefinition::ENTITY_NAME, self::AFFECTED_QUERY, [], [], $limit, $offset);
    }
}
