<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Activity;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Database adapter for immutable security activity. No foreign keys: deleting an
 * account or extension must not delete its history.
 *
 * @internal
 */
class ActivityStore
{
    private const ACTOR_LABEL = 'COALESCE(NULLIF(actor_name, \'\'), NULLIF(actor_id, \'\'), actor_type)';

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array<string, string|int|bool|list<string>> $context
     */
    public function append(string $action, int $level, array $context, \DateTimeImmutable $createdAt): string
    {
        $id = Uuid::randomBytes();
        $actorId = $context['userId'] ?? $context['integrationId'] ?? $context['integrationAccessKey'] ?? null;
        $actorName = $context['username'] ?? $context['loginUsername'] ?? null;
        $this->connection->insert('frosh_tools_security_activity', [
            'id' => $id,
            'action' => $action,
            'actor_type' => $context['actorType'] ?? 'unknown',
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'level' => $level,
            'client_ip' => isset($context['clientIp']) && is_string($context['clientIp']) ? (inet_pton($context['clientIp']) ?: null) : null,
            'context' => json_encode($context, \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE),
            'created_at' => $createdAt->setTimezone(new \DateTimeZone('UTC'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return Uuid::fromBytesToHex($id);
    }

    /**
     * @return array{entries: list<array<string, mixed>>, total: int}
     */
    public function search(int $page, int $limit, string $action, string $actor, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to, bool $exact = false, string $clientIp = '', string $userId = '', string $subjectType = '', string $subjectId = ''): array
    {
        [$where, $parameters] = $this->filters($action, $actor, $from, $to, $exact, $clientIp, $userId, $subjectType, $subjectId);
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM frosh_tools_security_activity' . $where, $parameters);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, action, level, context, created_at FROM frosh_tools_security_activity' . $where
            . ' ORDER BY created_at DESC, id DESC LIMIT ' . $limit . ' OFFSET ' . (($page - 1) * $limit),
            $parameters,
        );

        return ['entries' => array_map($this->entry(...), $rows), 'total' => $total];
    }

    /**
     * Export uses a descending cursor so new events do not shift later pages.
     * Retention can still remove records while an export is running.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function export(string $action, string $actor, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to, bool $exact = false, string $clientIp = '', string $userId = '', string $subjectType = '', string $subjectId = ''): \Generator
    {
        [$where, $parameters] = $this->filters($action, $actor, $from, $to, $exact, $clientIp, $userId, $subjectType, $subjectId);
        $cursor = null;
        do {
            $queryWhere = $where;
            $queryParameters = $parameters;
            if ($cursor !== null) {
                $queryWhere .= ($where === '' ? ' WHERE ' : ' AND ') . '(created_at < :cursorDate OR (created_at = :cursorDate AND id < :cursorId))';
                $queryParameters['cursorDate'] = $cursor['created_at'];
                $queryParameters['cursorId'] = $cursor['id'];
            }
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, action, level, context, created_at FROM frosh_tools_security_activity' . $queryWhere . ' ORDER BY created_at DESC, id DESC LIMIT 500',
                $queryParameters,
            );
            foreach ($rows as $row) {
                $cursor = $row;
                yield $this->entry($row);
            }
        } while (\count($rows) === 500);
    }

    /**
     * @return array{values: list<string>, hasMore: bool}
     */
    public function filterOptions(string $field, string $term, int $page = 1): array
    {
        $column = match ($field) {
            'action' => 'action',
            'actor' => self::ACTOR_LABEL,
            default => throw new \InvalidArgumentException('Unknown activity filter.'),
        };
        $values = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT ' . $column . ' AS value FROM frosh_tools_security_activity'
            . ' WHERE ' . $column . ' LIKE :term ESCAPE \'!\' ORDER BY value LIMIT 51 OFFSET ' . (($page - 1) * 50),
            ['term' => $this->prefix($term)],
        );

        return ['values' => array_slice($values, 0, 50), 'hasMore' => \count($values) > 50];
    }

    public function deleteBefore(\DateTimeImmutable $cutoff): void
    {
        $this->connection->executeStatement('DELETE FROM frosh_tools_security_activity WHERE created_at < :cutoff', [
            'cutoff' => $cutoff->setTimezone(new \DateTimeZone('UTC'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function entry(array $row): array
    {
        return [
            'id' => Uuid::fromBytesToHex($row['id']),
            'action' => $row['action'],
            'level' => (int) $row['level'],
            'context' => json_decode($row['context'], true, flags: \JSON_THROW_ON_ERROR),
            'createdAt' => (new \DateTimeImmutable($row['created_at'], new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array{string, array<string, mixed>}
     */
    private function filters(string $action, string $actor, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to, bool $exact, string $clientIp, string $userId, string $subjectType, string $subjectId): array
    {
        $conditions = [];
        $parameters = [];
        if ($action !== '') {
            $conditions[] = $exact ? 'action = :action' : 'action LIKE :action ESCAPE \'!\'';
            $parameters['action'] = $exact ? $action : $this->prefix($action);
        }
        if ($actor !== '') {
            $conditions[] = $exact ? self::ACTOR_LABEL . ' = :actor' : '(actor_id LIKE :actor ESCAPE \'!\' OR actor_name LIKE :actor ESCAPE \'!\' OR actor_type LIKE :actor ESCAPE \'!\')';
            $parameters['actor'] = $exact ? $actor : $this->prefix($actor);
        }
        if ($clientIp !== '') {
            $conditions[] = 'client_ip = :clientIp';
            $parameters['clientIp'] = inet_pton($clientIp);
        }
        if ($userId !== '') {
            $conditions[] = 'actor_type = \'user\' AND actor_id = :userId';
            $parameters['userId'] = $userId;
        }
        if ($subjectId !== '') {
            $entityId = 'JSON_UNQUOTE(JSON_EXTRACT(context, \'$.entityId\')) = :subjectId';
            $conditions[] = match ($subjectType) {
                'users' => "((actor_type = 'user' AND actor_id = :subjectId) OR JSON_UNQUOTE(JSON_EXTRACT(context, '$.targetUserId')) = :subjectId OR ($entityId AND action LIKE 'user:%'))",
                'integrations' => "((actor_type = 'integration' AND actor_id = :subjectId) OR ($entityId AND (action LIKE 'integration:%' OR action IN ('integration_role:create', 'integration_role:update', 'integration_role:delete'))))",
                'keys' => "($entityId AND (action IN ('user_access_key:create', 'user_access_key:update', 'user_access_key:delete')))",
                default => throw new \InvalidArgumentException('Unknown activity subject.'),
            };
            $parameters['subjectId'] = $subjectId;
        }
        if ($from !== null) {
            $conditions[] = 'created_at >= :from';
            $parameters['from'] = $from->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        }
        if ($to !== null) {
            $conditions[] = 'created_at < :to';
            $parameters['to'] = $to->modify('+1 day')->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        return [$where, $parameters];
    }

    private function prefix(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value) . '%';
    }
}
