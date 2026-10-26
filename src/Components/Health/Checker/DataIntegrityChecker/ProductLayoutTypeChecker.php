<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductLayoutTypeChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'product-layout-wrong-type';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT p.id
        FROM product p
        INNER JOIN cms_page cp
            ON cp.id = p.cms_page_id
            AND cp.version_id = p.cms_page_version_id
        WHERE p.version_id = :liveVersionId
          AND cp.type != 'product_detail'
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
            'Products with a layout that is not a product page layout',
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
