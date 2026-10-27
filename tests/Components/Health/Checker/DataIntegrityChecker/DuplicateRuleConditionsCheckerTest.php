<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\DuplicateRuleConditionsChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(DuplicateRuleConditionsChecker::class)]
class DuplicateRuleConditionsCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'rule-duplicate-conditions';

    private DuplicateRuleConditionsChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(DuplicateRuleConditionsChecker::class);
        $this->connection->executeStatement('DELETE FROM rule_condition');
    }

    public function testReportsOkWithoutDuplicates(): void
    {
        $this->createRule([self::andTree([self::cartAmount(100)])]);
        $this->createRule([self::andTree([self::cartAmount(200)])]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
        static::assertAffectedEntities($this->checker, 'rule', []);
    }

    public function testIgnoresConditionAndValueOrder(): void
    {
        $germany = Uuid::randomHex();
        $austria = Uuid::randomHex();

        $first = $this->createRule([self::andTree([
            self::billingCountry([$germany, $austria]),
            self::cartAmount(100),
        ])]);
        $second = $this->createRule([self::andTree([
            self::cartAmount(100),
            ['customerBillingCountry', ['countryIds' => [$austria, $germany], 'operator' => '=']],
        ])]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
        static::assertAffectedEntities($this->checker, 'rule', [$first, $second]);
    }

    public function testComparesValuesAndTreeShape(): void
    {
        $this->createRule([self::andTree([self::cartAmount(100), self::dayOfWeek(7)])]);
        $this->createRule([self::andTree([self::cartAmount(100), self::dayOfWeek(6)])]);
        $this->createRule([['orContainer', null, [
            ['andContainer', null, [self::cartAmount(100)]],
            ['andContainer', null, [self::dayOfWeek(7)]],
        ]]]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testIgnoresRulesWithOnlyContainers(): void
    {
        $this->createRule([['orContainer', null, [['andContainer']]]]);
        $this->createRule([['orContainer', null, [['andContainer']]]]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testListsDuplicateSetsNextToEachOther(): void
    {
        $ids = [
            '0f000000000000000000000000000001',
            '0f000000000000000000000000000002',
            '0f000000000000000000000000000003',
            '0f000000000000000000000000000004',
            '0f000000000000000000000000000005',
        ];

        $this->createRule([self::andTree([self::cartAmount(100)])], $ids[0]);
        $this->createRule([self::andTree([self::dayOfWeek(7)])], $ids[1]);
        $this->createRule([self::andTree([self::cartAmount(100)])], $ids[2]);
        $this->createRule([self::andTree([self::dayOfWeek(7)])], $ids[3]);
        $this->createRule([self::andTree([self::cartAmount(100)])], $ids[4]);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 5);

        $affected = $this->checker->getAffectedEntities(100, 0);
        static::assertSame('rule', $affected->entity);
        static::assertSame(5, $affected->total);
        static::assertSame([$ids[0], $ids[2], $ids[4], $ids[1], $ids[3]], $affected->ids);

        static::assertSame([
            $ids[0] => [$ids[2], $ids[4]],
            $ids[2] => [$ids[0], $ids[4]],
            $ids[4] => [$ids[0], $ids[2]],
            $ids[1] => [$ids[3]],
            $ids[3] => [$ids[1]],
        ], $affected->related);

        $page = $this->checker->getAffectedEntities(2, 2);
        static::assertSame(5, $page->total);
        static::assertSame([$ids[4], $ids[1]], $page->ids);
        static::assertSame([$ids[4] => [$ids[0], $ids[2]], $ids[1] => [$ids[3]]], $page->related);
    }

    public function testSortsNumericLookingRuleIdsAsStrings(): void
    {
        $ids = ['1e000000000000000000000000000001', '10000000000000000000000000000002'];
        $this->createRule([self::andTree([self::cartAmount(100)])], $ids[0]);
        $this->createRule([self::andTree([self::cartAmount(100)])], $ids[1]);

        static::assertSame([$ids[1], $ids[0]], $this->checker->getAffectedEntities(100, 0)->ids);
    }

    /**
     * @param list<array<mixed>> $children
     *
     * @return array<mixed>
     */
    private static function andTree(array $children): array
    {
        return ['orContainer', null, [['andContainer', null, $children]]];
    }

    /**
     * @return array<mixed>
     */
    private static function cartAmount(int $amount): array
    {
        return ['cartCartAmount', ['operator' => '>=', 'amount' => $amount]];
    }

    /**
     * @return array<mixed>
     */
    private static function dayOfWeek(int $day): array
    {
        return ['dayOfWeek', ['operator' => '=', 'dayOfWeek' => $day]];
    }

    /**
     * @param list<string> $countryIds
     *
     * @return array<mixed>
     */
    private static function billingCountry(array $countryIds): array
    {
        return ['customerBillingCountry', ['operator' => '=', 'countryIds' => $countryIds]];
    }

    /**
     * @param list<array<mixed>> $tree
     */
    private function createRule(array $tree, ?string $id = null): string
    {
        $id ??= Uuid::randomHex();

        $this->connection->insert('rule', [
            'id' => Uuid::fromHexToBytes($id),
            'name' => 'Frosh data integrity check rule',
            'priority' => 1,
            'created_at' => self::now(),
        ]);

        $this->createConditions($id, null, $tree);

        return $id;
    }

    /**
     * @param list<array<mixed>> $nodes
     */
    private function createConditions(string $ruleId, ?string $parentId, array $nodes): void
    {
        foreach ($nodes as $position => $node) {
            $id = Uuid::randomHex();

            $this->connection->insert('rule_condition', [
                'id' => Uuid::fromHexToBytes($id),
                'type' => $node[0],
                'rule_id' => Uuid::fromHexToBytes($ruleId),
                'parent_id' => $parentId === null ? null : Uuid::fromHexToBytes($parentId),
                'value' => isset($node[1]) ? json_encode($node[1], \JSON_THROW_ON_ERROR) : null,
                'position' => $position,
                'created_at' => self::now(),
            ]);

            $this->createConditions($ruleId, $id, $node[2] ?? []);
        }
    }
}
