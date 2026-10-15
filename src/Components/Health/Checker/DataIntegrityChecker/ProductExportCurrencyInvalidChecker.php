<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ProductExportCurrencyInvalidChecker implements DataIntegrityCheckerInterface, CheckerInterface
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.shopware_version%')]
        private readonly string $shopwareVersion,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $count = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM product_export pe
                LEFT JOIN currency
                    ON currency.id = pe.currency_id
                LEFT JOIN sales_channel_currency assigned
                    ON assigned.sales_channel_id = pe.storefront_sales_channel_id
                    AND assigned.currency_id = pe.currency_id
                WHERE currency.id IS NULL
                   OR (
                       :currencyMustBeAssigned = 1
                       AND pe.storefront_sales_channel_id IS NOT NULL
                       AND assigned.currency_id IS NULL
                   )
                SQL,
            ['currencyMustBeAssigned' => (int) version_compare($this->shopwareVersion, '6.7.15.0', '>=')],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'product-export-currency-invalid',
            'Product comparisons with a missing or unassigned currency',
            $count,
        ));
    }
}
