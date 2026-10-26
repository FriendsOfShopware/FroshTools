<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\InvalidRuleChecker;
use Shopware\Core\Framework\Uuid\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InvalidRuleChecker::class)]
class InvalidRuleCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'rule-invalid';

    private InvalidRuleChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(InvalidRuleChecker::class);
        $this->connection->executeStatement('UPDATE `rule` SET invalid = 0');
    }

    public function testReportsOkWithoutInvalidRules(): void
    {
        $this->createRule(false);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
        static::assertAffectedEntities($this->checker, 'rule', []);
    }

    public function testCountsInvalidRules(): void
    {
        $first = $this->createRule(true);
        $second = $this->createRule(true);
        $this->createRule(false);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
        static::assertAffectedEntities($this->checker, 'rule', [$first, $second]);
    }

    private function createRule(bool $invalid): string
    {
        $id = Uuid::randomHex();

        $this->connection->insert('rule', [
            'id' => Uuid::fromHexToBytes($id),
            'name' => 'Frosh data integrity check rule',
            'priority' => 1,
            'invalid' => (int) $invalid,
            'created_at' => self::now(),
        ]);

        return $id;
    }
}
