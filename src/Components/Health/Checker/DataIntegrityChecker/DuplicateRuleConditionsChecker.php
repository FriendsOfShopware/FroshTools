<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Rule\Container\ContainerInterface;

/**
 * @phpstan-type ConditionRow array{rule_id: string, id: string, parent_id: string|null, type: string, value: string|null, script_id: string|null}
 */
class DuplicateRuleConditionsChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'rule-duplicate-conditions';

    public function __construct(
        private readonly Connection $connection,
        private readonly RuleConditionRegistry $ruleConditionRegistry,
    ) {
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function collect(HealthCollection $collection): void
    {
        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Rules with identical conditions',
            \count(array_merge(...$this->findDuplicateGroups())),
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        $groups = $this->findDuplicateGroups();

        $ids = array_merge(...$groups);
        $pageIds = \array_slice($ids, $offset, $limit);

        $related = [];
        foreach ($groups as $group) {
            foreach (array_intersect($group, $pageIds) as $id) {
                $related[$id] = array_values(array_diff($group, [$id]));
            }
        }

        return new AffectedEntities(RuleDefinition::ENTITY_NAME, \count($ids), $pageIds, $related);
    }

    /**
     * @return list<list<string>>
     */
    private function findDuplicateGroups(): array
    {
        /** @var list<ConditionRow> $rows */
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT LOWER(HEX(rule_id)) AS rule_id,
                       LOWER(HEX(id)) AS id,
                       LOWER(HEX(parent_id)) AS parent_id,
                       type,
                       value,
                       LOWER(HEX(script_id)) AS script_id
                FROM rule_condition
                SQL,
        );

        $conditionsByRule = [];
        foreach ($rows as $row) {
            $conditionsByRule[$row['rule_id']][] = $row;
        }

        $rulesByChecksum = [];
        foreach ($conditionsByRule as $ruleId => $conditions) {
            $checksum = $this->checksum($conditions);
            if ($checksum !== null) {
                $rulesByChecksum[$checksum][] = (string) $ruleId;
            }
        }

        $groups = [];
        foreach ($rulesByChecksum as $ruleIds) {
            if (\count($ruleIds) > 1) {
                sort($ruleIds);
                $groups[] = $ruleIds;
            }
        }

        usort($groups, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return $groups;
    }

    /**
     * @param list<ConditionRow> $conditions
     */
    private function checksum(array $conditions): ?string
    {
        foreach ($conditions as $condition) {
            if (!$this->isContainer($condition['type'])) {
                return hash('xxh128', json_encode($this->normalize($conditions), \JSON_THROW_ON_ERROR));
            }
        }

        return null;
    }

    /**
     * @param list<ConditionRow> $conditions
     */
    private function normalize(array $conditions): mixed
    {
        $childrenByParent = [];
        foreach ($conditions as $condition) {
            $childrenByParent[$condition['parent_id'] ?? ''][] = $condition;
        }

        return $this->canonicalize($this->buildTree($childrenByParent, ''));
    }

    /**
     * @param array<string, list<ConditionRow>> $childrenByParent
     *
     * @return list<array{type: string, scriptId: string|null, value: mixed, children: list<mixed>}>
     */
    private function buildTree(array $childrenByParent, string $parentId): array
    {
        $nodes = [];
        foreach ($childrenByParent[$parentId] ?? [] as $condition) {
            $nodes[] = [
                'type' => $condition['type'],
                'scriptId' => $condition['script_id'],
                'value' => json_decode($condition['value'] ?? '{}', true) ?? [],
                'children' => $this->buildTree($childrenByParent, $condition['id']),
            ];
        }

        return $nodes;
    }

    private function isContainer(string $type): bool
    {
        return $this->ruleConditionRegistry->has($type)
            && is_a($this->ruleConditionRegistry->getRuleClass($type), ContainerInterface::class, true);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        $value = array_map($this->canonicalize(...), $value);

        if (!array_is_list($value)) {
            ksort($value, \SORT_STRING);

            return $value;
        }

        $sortKeys = array_map(static fn (mixed $item): string => json_encode($item, \JSON_THROW_ON_ERROR), $value);
        asort($sortKeys, \SORT_STRING);

        return array_map(static fn (int $index): mixed => $value[$index], array_keys($sortKeys));
    }
}
