<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class DuplicateCustomerEmailsChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'duplicate-customer-emails';

    private const DUPLICATE_CONDITION = <<<'SQL'
        other.guest = 0
        AND other.id != c.id
        AND other.email = c.email
        AND CAST(LOWER(other.email) AS BINARY) = CAST(LOWER(c.email) AS BINARY)
        AND (:isBoundToSalesChannel = 0
            OR c.bound_sales_channel_id IS NULL
            OR other.bound_sales_channel_id IS NULL
            OR other.bound_sales_channel_id = c.bound_sales_channel_id)
        SQL;

    private const AFFECTED_QUERY = 'SELECT c.id FROM customer c WHERE c.guest = 0'
        . ' AND EXISTS (SELECT 1 FROM customer other WHERE ' . self::DUPLICATE_CONDITION . ')';

    private const RELATED_QUERY = 'SELECT LOWER(HEX(c.id)) AS id, LOWER(HEX(other.id)) AS duplicate_id'
        . ' FROM customer c'
        . ' INNER JOIN customer other ON ' . self::DUPLICATE_CONDITION
        . ' WHERE c.guest = 0 AND c.id IN (:ids)'
        . ' ORDER BY other.id';

    public function __construct(
        private readonly Connection $connection,
        private readonly SystemConfigService $systemConfigService,
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
        $count = AffectedEntities::countRows(
            $this->connection,
            self::AFFECTED_QUERY,
            $this->getParameters(),
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Customers sharing an email address',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        $affected = AffectedEntities::fromQuery($this->connection, CustomerDefinition::ENTITY_NAME, self::AFFECTED_QUERY, $this->getParameters(), [], $limit, $offset);

        return new AffectedEntities($affected->entity, $affected->total, $affected->ids, $this->findDuplicates($affected->ids));
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, list<string>>
     */
    private function findDuplicates(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var list<array{id: string, duplicate_id: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            self::RELATED_QUERY,
            [...$this->getParameters(), 'ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY],
        );

        $duplicates = array_fill_keys($ids, []);
        foreach ($rows as $row) {
            $duplicates[$row['id']][] = $row['duplicate_id'];
        }

        return $duplicates;
    }

    /**
     * @return array{isBoundToSalesChannel: int}
     */
    private function getParameters(): array
    {
        $isBoundToSalesChannel = $this->systemConfigService->getBool('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel');

        return ['isBoundToSalesChannel' => (int) $isBoundToSalesChannel];
    }
}
