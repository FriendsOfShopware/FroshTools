<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\InvalidFlowChecker;
use Shopware\Core\Framework\Uuid\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InvalidFlowChecker::class)]
class InvalidFlowCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'flow-invalid';

    private InvalidFlowChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(InvalidFlowChecker::class);
        $this->connection->executeStatement('UPDATE flow SET invalid = 0');
    }

    public function testReportsOkWithoutActiveInvalidFlows(): void
    {
        $this->createFlow(active: true, invalid: false);
        $this->createFlow(active: false, invalid: true);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
        static::assertAffectedEntities($this->checker, 'flow', []);
    }

    public function testCountsActiveInvalidFlows(): void
    {
        $flowId = $this->createFlow(active: true, invalid: true);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 1);
        static::assertAffectedEntities($this->checker, 'flow', [$flowId]);
    }

    private function createFlow(bool $active, bool $invalid): string
    {
        $id = Uuid::randomHex();

        $this->connection->insert('flow', [
            'id' => Uuid::fromHexToBytes($id),
            'name' => 'Frosh data integrity check flow',
            'event_name' => 'checkout.order.placed',
            'active' => (int) $active,
            'invalid' => (int) $invalid,
            'created_at' => self::now(),
        ]);

        return $id;
    }
}
