<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ProductExportCurrencyInvalidChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'product-export-currency-invalid';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT pe.id, pe.sales_channel_id
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
        SQL;

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

    public function collect(HealthCollection $collection): void
    {
        $count = (int) $this->connection->fetchOne(
            \sprintf('SELECT COUNT(*) FROM (%s) affected', self::AFFECTED_QUERY),
            $this->queryParameters(),
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Product comparisons with a missing or unassigned currency',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery(
            $this->connection,
            SalesChannelDefinition::ENTITY_NAME,
            \sprintf('SELECT affected.sales_channel_id AS id FROM (%s) affected', self::AFFECTED_QUERY),
            $this->queryParameters(),
            [],
            $limit,
            $offset,
        );
    }

    /**
     * @return array{currencyMustBeAssigned: int}
     */
    private function queryParameters(): array
    {
        return ['currencyMustBeAssigned' => (int) version_compare($this->shopwareVersion, '6.7.15.0', '>=')];
    }
}
