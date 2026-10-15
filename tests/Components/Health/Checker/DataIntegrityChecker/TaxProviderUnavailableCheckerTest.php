<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\TaxProviderUnavailableChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(TaxProviderUnavailableChecker::class)]
class TaxProviderUnavailableCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'tax-provider-unavailable';

    private TaxProviderUnavailableChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(TaxProviderUnavailableChecker::class);
        $this->connection->executeStatement('UPDATE tax_provider SET active = 0');
    }

    public function testReportsOkForInactiveProvidersWithoutHandler(): void
    {
        $this->createTaxProvider('Frosh\\Tools\\Tests\\RemovedTaxProvider', false);
        $this->createTaxProvider('app\\FroshApp_tax', true, $this->createApp(true));

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsActiveProvidersWithoutRegisteredHandler(): void
    {
        $this->createTaxProvider('Frosh\\Tools\\Tests\\RemovedTaxProvider', true);
        $this->createTaxProvider('Frosh\\Tools\\Tests\\OtherRemovedTaxProvider', false);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 1);
    }

    private function createTaxProvider(string $identifier, bool $active, ?string $appId = null): void
    {
        $this->connection->insert('tax_provider', [
            'id' => Uuid::randomBytes(),
            'identifier' => $identifier,
            'active' => (int) $active,
            'priority' => 1,
            'app_id' => $appId,
            'process_url' => $appId === null ? null : 'https://example.com/tax',
            'created_at' => self::now(),
        ]);
    }
}
