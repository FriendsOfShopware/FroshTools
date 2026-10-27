<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxProviderRegistry;
use Shopware\Core\System\TaxProvider\TaxProviderDefinition;

class TaxProviderUnavailableChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'tax-provider-unavailable';

    public function __construct(
        private readonly Connection $connection,
        private readonly TaxProviderRegistry $taxProviderRegistry,
    ) {
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function getAffectedEntity(): string
    {
        return TaxProviderDefinition::ENTITY_NAME;
    }

    public function collect(HealthCollection $collection): void
    {
        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Active tax providers that are not available',
            \count($this->findAffectedIds()),
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromIds(TaxProviderDefinition::ENTITY_NAME, $this->findAffectedIds(), $limit, $offset);
    }

    /**
     * @return list<string>
     */
    private function findAffectedIds(): array
    {
        /** @var list<array{id: string, identifier: string}> $taxProviders */
        $taxProviders = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT LOWER(HEX(id)) AS id, identifier
                FROM tax_provider
                WHERE active = 1
                  AND (app_id IS NULL OR process_url IS NULL)
                SQL,
        );

        $ids = [];
        foreach ($taxProviders as $taxProvider) {
            if (!$this->taxProviderRegistry->has($taxProvider['identifier'])) {
                $ids[] = $taxProvider['id'];
            }
        }

        return $ids;
    }
}
