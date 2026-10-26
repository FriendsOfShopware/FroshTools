<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\System\DeliveryTime\DeliveryTimeDefinition;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;

class DeliveryTimeInvalidChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'delivery-time-invalid';

    private const SUPPORTED_UNITS = [
        DeliveryTimeEntity::DELIVERY_TIME_HOUR,
        DeliveryTimeEntity::DELIVERY_TIME_DAY,
        DeliveryTimeEntity::DELIVERY_TIME_WEEK,
        DeliveryTimeEntity::DELIVERY_TIME_MONTH,
        DeliveryTimeEntity::DELIVERY_TIME_YEAR,
    ];

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT id
        FROM delivery_time
        WHERE CAST(unit AS BINARY) NOT IN (:supportedUnits)
           OR `min` < 0
           OR `max` < 0
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
        $count = (int) $this->connection->fetchOne(
            \sprintf('SELECT COUNT(*) FROM (%s) affected', self::AFFECTED_QUERY),
            ['supportedUnits' => self::SUPPORTED_UNITS],
            ['supportedUnits' => ArrayParameterType::STRING],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Delivery times with an unsupported unit or a negative duration',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery(
            $this->connection,
            DeliveryTimeDefinition::ENTITY_NAME,
            self::AFFECTED_QUERY,
            ['supportedUnits' => self::SUPPORTED_UNITS],
            ['supportedUnits' => ArrayParameterType::STRING],
            $limit,
            $offset,
        );
    }
}
