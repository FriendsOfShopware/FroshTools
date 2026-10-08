<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;

class DeliveryTimeInvalidChecker implements DataIntegrityCheckerInterface, CheckerInterface
{
    private const SUPPORTED_UNITS = [
        DeliveryTimeEntity::DELIVERY_TIME_HOUR,
        DeliveryTimeEntity::DELIVERY_TIME_DAY,
        DeliveryTimeEntity::DELIVERY_TIME_WEEK,
        DeliveryTimeEntity::DELIVERY_TIME_MONTH,
        DeliveryTimeEntity::DELIVERY_TIME_YEAR,
    ];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $count = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM delivery_time
                WHERE CAST(unit AS BINARY) NOT IN (:supportedUnits)
                   OR `min` < 0
                   OR `max` < 0
                SQL,
            ['supportedUnits' => self::SUPPORTED_UNITS],
            ['supportedUnits' => ArrayParameterType::STRING],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'delivery-time-invalid',
            'Delivery times with an unsupported unit or a negative duration',
            $count,
        ));
    }
}
