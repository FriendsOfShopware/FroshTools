<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ProductMaxPurchaseBelowMinPurchaseChecker implements DataIntegrityCheckerInterface, CheckerInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $count = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM (SELECT p.id,
                             p.version_id,
                             COALESCE(p.max_purchase, parent.max_purchase, :maxQuantity) AS max_purchase,
                             COALESCE(p.min_purchase, parent.min_purchase, 1) AS min_purchase,
                             COALESCE(p.purchase_steps, parent.purchase_steps, 1) AS purchase_steps
                      FROM product p
                      LEFT JOIN product parent
                          ON parent.id = p.parent_id
                          AND parent.version_id = p.version_id
                      WHERE p.version_id = :liveVersionId) p
                WHERE p.max_purchase < p.min_purchase
                  AND FLOOR((p.max_purchase - p.min_purchase) / p.purchase_steps) * p.purchase_steps + p.min_purchase > 0
                  AND NOT EXISTS (
                      SELECT 1
                      FROM product child
                      WHERE child.parent_id = p.id
                        AND child.version_id = p.version_id
                  )
                SQL,
            [
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'maxQuantity' => $this->systemConfigService->getInt('core.cart.maxQuantity'),
            ],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'product-max-purchase-below-min-purchase',
            'Products with a maximum order quantity below the minimum order quantity',
            $count,
        ));
    }
}
