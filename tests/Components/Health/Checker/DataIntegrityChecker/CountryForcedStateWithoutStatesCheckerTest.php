<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\CountryForcedStateWithoutStatesChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(CountryForcedStateWithoutStatesChecker::class)]
class CountryForcedStateWithoutStatesCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'country-forced-state-without-states';

    private CountryForcedStateWithoutStatesChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(CountryForcedStateWithoutStatesChecker::class);
        $this->connection->executeStatement(
            'UPDATE country c SET force_state_in_registration = 0 WHERE NOT EXISTS (SELECT 1 FROM country_state cs WHERE cs.country_id = c.id AND cs.active = 1)'
        );
    }

    public function testReportsOkForCountriesWithActiveStatesOrNotInUse(): void
    {
        $withStates = $this->createCountry(true, true);
        $this->createState($withStates, true);

        $this->createCountry(true, false);
        $this->createCountry(false, true);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsAssignedActiveCountriesRequiringAStateWithoutActiveStates(): void
    {
        $this->createCountry(true, true);

        $onlyInactiveStates = $this->createCountry(true, true);
        $this->createState($onlyInactiveStates, false);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }

    private function createCountry(bool $active, bool $assigned): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('country', [
            'id' => $id,
            'iso' => 'XX',
            'active' => (int) $active,
            'force_state_in_registration' => 1,
            'created_at' => self::now(),
        ]);

        if ($assigned) {
            $this->connection->insert('sales_channel_country', [
                'sales_channel_id' => $this->connection->fetchOne('SELECT id FROM sales_channel LIMIT 1'),
                'country_id' => $id,
            ]);
        }

        return $id;
    }

    private function createState(string $countryId, bool $active): void
    {
        $this->connection->insert('country_state', [
            'id' => Uuid::randomBytes(),
            'country_id' => $countryId,
            'short_code' => 'XX-' . Uuid::randomHex(),
            'active' => (int) $active,
            'created_at' => self::now(),
        ]);
    }
}
