<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Activity;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
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
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array<string, string|int|bool|list<string>> $context
     */
    public function append(string $action, int $level, array $context, \DateTimeImmutable $createdAt): string
    {
        // Some core versions only supply the integration's access key.
        if (!isset($context['userId']) && !isset($context['integrationId']) && \is_string($context['integrationAccessKey'] ?? null)) {
            try {
                $integrationId = $this->connection->createQueryBuilder()
                    ->select('LOWER(HEX(id))')
                    ->from('integration')
                    ->where('access_key = :accessKey')
                    ->setParameter('accessKey', $context['integrationAccessKey'])
                    ->executeQuery()
                    ->fetchOne();
                if (\is_string($integrationId)) {
                    $context['integrationId'] = $integrationId;
                }
            } catch (\Throwable) {
                // Preserve the event even when optional identity enrichment fails.
            }
        }
        $id = Uuid::randomBytes();
        $actorId = $context['userId'] ?? $context['integrationId'] ?? $context['integrationAccessKey'] ?? null;
        $actorName = $context['username'] ?? $context['loginUsername'] ?? null;
        $clientIp = isset($context['clientIp']) && \is_string($context['clientIp']) ? inet_pton($context['clientIp']) : false;
        $this->connection->insert('frosh_tools_security_activity', [
            'id' => $id,
            'action' => $action,
            'actor_type' => $context['actorType'] ?? 'unknown',
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'level' => $level,
            'client_ip' => $clientIp === false ? null : $clientIp,
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
        $query = $this->filteredQuery($action, $actor, $from, $to, $exact, $clientIp, $userId, $subjectType, $subjectId);
        $total = (int) (clone $query)->select('COUNT(*)')->executeQuery()->fetchOne();
        $rows = $query
            ->select('id', 'action', 'level', 'context', 'created_at')
            ->orderBy('created_at', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult(($page - 1) * $limit)
            ->executeQuery()
            ->fetchAllAssociative();

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
        $query = $this->filteredQuery($action, $actor, $from, $to, $exact, $clientIp, $userId, $subjectType, $subjectId)
            ->select('id', 'action', 'level', 'context', 'created_at')
            ->orderBy('created_at', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->setMaxResults(500);
        $cursor = null;
        do {
            $batch = clone $query;
            if ($cursor !== null) {
                $expression = $batch->expr();
                $batch->andWhere($expression->or(
                    $expression->lt('created_at', ':cursorDate'),
                    $expression->and(
                        $expression->eq('created_at', ':cursorDate'),
                        $expression->lt('id', ':cursorId'),
                    ),
                ))
                    ->setParameter('cursorDate', $cursor['created_at'])
                    ->setParameter('cursorId', $cursor['id']);
            }
            $rows = $batch->executeQuery()->fetchAllAssociative();
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
            'actor' => 'actor_label',
            default => throw new \InvalidArgumentException('Unknown activity filter.'),
        };
        $query = $this->connection->createQueryBuilder();
        $values = $query
            ->select($column)
            ->distinct()
            ->from('frosh_tools_security_activity')
            ->where($query->expr()->like($column, ':term', '\'!\''))
            ->setParameter('term', $this->prefix($term))
            ->orderBy($column)
            ->setMaxResults(51)
            ->setFirstResult(($page - 1) * 50)
            ->executeQuery()
            ->fetchFirstColumn();

        return ['values' => \array_slice($values, 0, 50), 'hasMore' => \count($values) > 50];
    }

    public function deleteBefore(\DateTimeImmutable $cutoff): void
    {
        $this->connection->createQueryBuilder()
            ->delete('frosh_tools_security_activity')
            ->where('created_at < :cutoff')
            ->setParameter('cutoff', $cutoff->setTimezone(new \DateTimeZone('UTC'))->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->executeStatement();
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

    private function filteredQuery(string $action, string $actor, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to, bool $exact, string $clientIp, string $userId, string $subjectType, string $subjectId): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()->from('frosh_tools_security_activity');
        $expression = $query->expr();
        if ($action !== '') {
            $query->andWhere($exact ? $expression->eq('action', ':action') : $expression->like('action', ':action', '\'!\''))
                ->setParameter('action', $exact ? $action : $this->prefix($action));
        }
        if ($actor !== '') {
            $query->andWhere($exact ? $expression->eq('actor_label', ':actor') : $expression->or(
                $expression->like('actor_id', ':actor', '\'!\''),
                $expression->like('actor_name', ':actor', '\'!\''),
                $expression->like('actor_type', ':actor', '\'!\''),
            ))
                ->setParameter('actor', $exact ? $actor : $this->prefix($actor));
        }
        if ($clientIp !== '') {
            $query->andWhere('client_ip = :clientIp')->setParameter('clientIp', inet_pton($clientIp));
        }
        if ($userId !== '') {
            $query->andWhere('actor_type = \'user\'')->andWhere('actor_id = :userId')->setParameter('userId', $userId);
        }
        if ($subjectId !== '') {
            $entityId = $expression->eq('JSON_UNQUOTE(JSON_EXTRACT(context, \'$.entityId\'))', ':subjectId');
            $query->andWhere(match ($subjectType) {
                'users' => $expression->or(
                    $expression->and('actor_type = \'user\'', 'actor_id = :subjectId'),
                    $expression->eq('JSON_UNQUOTE(JSON_EXTRACT(context, \'$.targetUserId\'))', ':subjectId'),
                    $expression->and($entityId, 'action LIKE \'user:%\''),
                ),
                'integrations' => $expression->or(
                    $expression->and('actor_type = \'integration\'', 'actor_id = :subjectId'),
                    $expression->and($entityId, $expression->or(
                        'action LIKE \'integration:%\'',
                        'action IN (\'integration_role:create\', \'integration_role:update\', \'integration_role:delete\')',
                    )),
                ),
                'keys' => $expression->and($entityId, 'action IN (\'user_access_key:create\', \'user_access_key:update\', \'user_access_key:delete\')'),
                default => throw new \InvalidArgumentException('Unknown activity subject.'),
            })
                ->setParameter('subjectId', $subjectId);
        }
        if ($from !== null) {
            $query->andWhere('created_at >= :from')->setParameter('from', $from->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        }
        if ($to !== null) {
            $query->andWhere('created_at < :to')->setParameter('to', $to->modify('+1 day')->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        }

        return $query;
    }

    private function prefix(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value) . '%';
    }
}
