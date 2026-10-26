<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;

final class AffectedEntities implements \JsonSerializable
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        public readonly string $entity,
        public readonly int $total,
        public readonly array $ids,
    ) {
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
        $distinctIds = \sprintf('SELECT DISTINCT affected_query.id FROM (%s) affected_query', $idQuery);

        $total = (int) $connection->fetchOne(
            \sprintf('SELECT COUNT(*) FROM (%s) affected', $distinctIds),
            $params,
            $types,
        );

        if ($total === 0 || $offset >= $total) {
            return new self($entity, $total, []);
        }

        /** @var list<string> $ids */
        $ids = $connection->fetchFirstColumn(
            \sprintf(
                'SELECT LOWER(HEX(affected.id)) FROM (%s) affected ORDER BY affected.id LIMIT %d OFFSET %d',
                $distinctIds,
                $limit,
                $offset,
            ),
            $params,
            $types,
        );

        return new self($entity, $total, $ids);
    }

    /**
     * @param list<string> $ids
     */
    public static function fromIds(string $entity, array $ids, int $limit, int $offset): self
    {
        $ids = array_values(array_unique($ids));
        sort($ids);

        return new self($entity, \count($ids), \array_slice($ids, $offset, $limit));
    }

    /**
     * @return array{entity: string, total: int, ids: list<string>}
     */
    public function jsonSerialize(): array
    {
        return [
            'entity' => $this->entity,
            'total' => $this->total,
            'ids' => $this->ids,
        ];
    }
}
