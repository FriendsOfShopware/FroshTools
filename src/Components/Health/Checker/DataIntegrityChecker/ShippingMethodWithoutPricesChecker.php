<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class ShippingMethodWithoutPricesChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'shipping-method-without-prices';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT sm.id
        FROM shipping_method sm
        WHERE sm.active = 1
          AND NOT EXISTS (SELECT 1
                          FROM shipping_method_price price
                          WHERE price.shipping_method_id = sm.id)
        SQL;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function collect(HealthCollection $collection): void
    {
        $count = (int) $this->connection->fetchOne(\sprintf('SELECT COUNT(*) FROM (%s) affected', self::AFFECTED_QUERY));

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Active shipping methods without prices',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery($this->connection, 'shipping_method', self::AFFECTED_QUERY, [], [], $limit, $offset);
    }
}
