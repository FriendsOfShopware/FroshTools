<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxProviderRegistry;

class TaxProviderUnavailableChecker implements DataIntegrityCheckerInterface, CheckerInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TaxProviderRegistry $taxProviderRegistry,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        /** @var list<string> $identifiers */
        $identifiers = $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT identifier
                FROM tax_provider
                WHERE active = 1
                  AND (app_id IS NULL OR process_url IS NULL)
                SQL,
        );

        $count = \count(array_filter(
            $identifiers,
            fn (string $identifier): bool => !$this->taxProviderRegistry->has($identifier),
        ));

        $collection->add(DataIntegrityCheckResult::fromCount(
            'tax-provider-unavailable',
            'Active tax providers that are not available',
            $count,
        ));
    }
}
