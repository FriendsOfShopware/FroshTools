<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductConfiguratorSettingsMissingChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'product-configurator-settings-missing';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT p.id
        FROM product p
        WHERE p.version_id = :liveVersionId
          AND p.parent_id IS NULL
          AND EXISTS (
              SELECT 1
              FROM product variant
              INNER JOIN product_option po
                  ON po.product_id = variant.id
                  AND po.product_version_id = variant.version_id
              WHERE variant.parent_id = p.id
                AND variant.version_id = p.version_id
          )
          AND NOT EXISTS (
              SELECT 1
              FROM product_configurator_setting pcs
              WHERE pcs.product_id = p.id
                AND pcs.product_version_id = p.version_id
          )
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
            ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Products with variants but without variant configurator settings',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery(
            $this->connection,
            ProductDefinition::ENTITY_NAME,
            self::AFFECTED_QUERY,
            ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            [],
            $limit,
            $offset,
        );
    }
}
