<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class AffectedEntities implements \JsonSerializable
{
    /**
     * @param list<string> $ids
     * @param array<string, list<string>> $related
     */
    public function __construct(
        public readonly string $entity,
        public readonly int $total,
        public readonly array $ids,
        public readonly array $related = [],
    ) {
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $types
     */
    public static function countRows(Connection $connection, string $query, array $params = [], array $types = []): int
    {
        return (int) $connection->fetchOne('SELECT COUNT(*) FROM (' . $query . ') counted', $params, $types);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $types
     */
    public static function fromQuery(
        Connection $connection,
        string $entity,
        string $idQuery,
        array $params,
        array $types,
        int $limit,
        int $offset,
    ): self {
        $distinctIds = 'SELECT DISTINCT affected_query.id FROM (' . $idQuery . ') affected_query';

        $total = self::countRows($connection, $distinctIds, $params, $types);

        if ($total === 0 || $offset >= $total) {
            return new self($entity, $total, []);
        }

        /** @var list<string> $ids */
        $ids = $connection->fetchFirstColumn(
            'SELECT LOWER(HEX(affected.id)) FROM (' . $distinctIds . ') affected ORDER BY affected.id LIMIT :affectedLimit OFFSET :affectedOffset',
            [...$params, 'affectedLimit' => $limit, 'affectedOffset' => $offset],
            [...$types, 'affectedLimit' => ParameterType::INTEGER, 'affectedOffset' => ParameterType::INTEGER],
        );

        return new self($entity, $total, $ids);
    }

    /**
     * @param list<string> $ids
     */
    public static function fromIds(string $entity, array $ids, int $limit, int $offset): self
    {
        $ids = array_values(array_unique($ids));
        sort($ids, \SORT_STRING);

        return new self($entity, \count($ids), \array_slice($ids, $offset, $limit));
    }

    /**
     * @return array{entity: string, total: int, ids: list<string>, related?: array<string, list<string>>}
     */
    public function jsonSerialize(): array
    {
        $data = [
            'entity' => $this->entity,
            'total' => $this->total,
            'ids' => $this->ids,
        ];

        if ($this->related !== []) {
            $data['related'] = $this->related;
        }

        return $data;
    }
}
