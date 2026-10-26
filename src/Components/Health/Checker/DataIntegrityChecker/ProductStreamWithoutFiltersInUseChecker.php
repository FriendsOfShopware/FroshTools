<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ProductStreamWithoutFiltersInUseChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'product-stream-without-filters-in-use';

    private const SLOT_CONFIG_COLUMNS = [
        ['cms_slot_translation', 'config', 'cms_slot_version_id'],
        ['category_translation', 'slot_config', 'category_version_id'],
        ['product_translation', 'slot_config', 'product_version_id'],
        ['landing_page_translation', 'slot_config', 'landing_page_version_id'],
        ['sales_channel_translation', 'home_slot_config', null],
    ];

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.shopware_version%')]
        private readonly string $shopwareVersion,
    ) {
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function getAffectedEntity(): string
    {
        return ProductStreamDefinition::ENTITY_NAME;
    }

    public function collect(HealthCollection $collection): void
    {
        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Dynamic product groups without filters that are still in use',
            \count($this->findAffectedIds()),
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromIds(ProductStreamDefinition::ENTITY_NAME, $this->findAffectedIds(), $limit, $offset);
    }

    /**
     * @return list<string>
     */
    private function findAffectedIds(): array
    {
        /** @var array<string, int|string> $streams */
        $streams = $this->connection->fetchAllKeyValue(
            <<<'SQL'
                SELECT LOWER(HEX(id)), (api_filter IS NULL OR invalid = 1)
                FROM product_stream
                WHERE api_filter IS NULL OR JSON_LENGTH(api_filter) = 0
                SQL,
        );

        if ($streams === []) {
            return [];
        }

        return array_keys($this->findUsedStreams($streams));
    }

    /**
     * @param array<string, int|string> $streams
     *
     * @return array<string, true>
     */
    private function findUsedStreams(array $streams): array
    {
        $streamIds = array_map(Uuid::fromHexToBytes(...), array_keys($streams));
        $parameters = ['streamIds' => $streamIds, 'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)];
        $types = ['streamIds' => ArrayParameterType::BINARY];

        $used = array_fill_keys($this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT LOWER(HEX(product_stream_id))
                FROM category
                WHERE product_stream_id IN (:streamIds)
                  AND version_id = :liveVersionId
                  AND product_assignment_type = 'product_stream'
                  AND active = 1
                  AND type = 'page'
                UNION
                SELECT LOWER(HEX(product_stream_id))
                FROM product_cross_selling
                WHERE product_stream_id IN (:streamIds)
                  AND product_version_id = :liveVersionId
                  AND type = 'productStream'
                  AND active = 1
                SQL,
            $parameters,
            $types,
        ), true);

        $emptyStreamExportsAllProducts = version_compare($this->shopwareVersion, '6.7.14.0', '>=');
        foreach ($this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(product_stream_id)) FROM product_export WHERE product_stream_id IN (:streamIds)',
            $parameters,
            $types,
        ) as $streamId) {
            if (!$emptyStreamExportsAllProducts || (int) $streams[$streamId] === 1) {
                $used[$streamId] = true;
            }
        }

        $pattern = implode('|', array_keys($streams));
        foreach (self::SLOT_CONFIG_COLUMNS as [$table, $column, $versionColumn]) {
            $sql = \sprintf('SELECT `%s` FROM `%s` WHERE `%s` REGEXP :pattern', $column, $table, $column);
            if ($versionColumn !== null) {
                $sql .= \sprintf(' AND `%s` = :liveVersionId', $versionColumn);
            }

            foreach ($this->connection->fetchFirstColumn($sql, ['pattern' => $pattern, 'liveVersionId' => $parameters['liveVersionId']]) as $slotConfig) {
                foreach (array_keys($streams) as $streamId) {
                    if (str_contains((string) $slotConfig, $streamId)) {
                        $used[$streamId] = true;
                    }
                }
            }
        }

        return $used;
    }
}
