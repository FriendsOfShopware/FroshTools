<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class QueueChecker implements HealthCheckerInterface, CheckerInterface
{
    private const CONFIG_GRACE = 'FroshTools.config.monitorQueueGraceTime';
    private const CONFIG_EXCLUDE_FAILED = 'FroshTools.config.monitorExcludeFailedQueues';
    private const CONFIG_QUEUES = 'FroshTools.config.monitorQueues';
    private const CONFIG_GRACE_TIMES = 'FroshTools.config.monitorQueueGraceTimes';
    private const DEFAULT_GRACE_MINUTES = 15;

    public function __construct(
        private readonly Connection $connection,
        private readonly SystemConfigService $configService,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $defaultGrace = $this->configService->getInt(self::CONFIG_GRACE) ?: self::DEFAULT_GRACE_MINUTES;
        $excludeFailed = $this->shouldExcludeFailedQueues();
        $queues = $this->parseCsvList($this->configService->getString(self::CONFIG_QUEUES));
        $graceByQueue = $this->parseGraceMap($this->configService->getString(self::CONFIG_GRACE_TIMES));

        $snippet = 'Open Queues';
        $row = $this->fetchOldestPendingMessage($excludeFailed, $queues);

        if ($row === null) {
            $collection->add(SettingsResult::info(
                'queue',
                $snippet,
                '0 mins',
                \sprintf('max %d mins', $defaultGrace),
            ));

            return;
        }

        $queueName = (string) $row['queue_name'];
        $grace = $graceByQueue[$queueName] ?? $defaultGrace;
        $recommended = \sprintf('max %d mins', $grace);
        $ageMinutes = $this->ageInMinutes((string) $row['available_at']);
        $current = \sprintf('%d mins (%s)', $ageMinutes, $queueName);

        if ($ageMinutes > $grace) {
            $collection->add(SettingsResult::warning('queue', $snippet, $current, $recommended));

            return;
        }

        $collection->add(SettingsResult::ok('queue', $snippet, $current, $recommended));
    }

    /**
     * @param list<string> $queues
     *
     * @return array{available_at: string, queue_name: string}|null
     */
    private function fetchOldestPendingMessage(bool $excludeFailed, array $queues): ?array
    {
        $sql = 'SELECT available_at, queue_name FROM messenger_messages WHERE available_at <= UTC_TIMESTAMP()';
        $params = [];

        if ($excludeFailed) {
            // Symfony failure transport names typically contain "failed" (e.g. async_failed).
            $sql .= ' AND queue_name NOT LIKE ?';
            $params[] = '%failed%';
        }

        if ($queues !== []) {
            $placeholders = \implode(', ', \array_fill(0, \count($queues), '?'));
            $sql .= \sprintf(' AND queue_name IN (%s)', $placeholders);
            foreach ($queues as $queue) {
                $params[] = $queue;
            }
        }

        $sql .= ' ORDER BY available_at ASC LIMIT 1';

        /** @var array{available_at: string, queue_name: string}|false $row */
        $row = $this->connection->fetchAssociative($sql, $params);

        return \is_array($row) ? $row : null;
    }

    private function ageInMinutes(string $availableAt): int
    {
        $available = new \DateTimeImmutable($availableAt, new \DateTimeZone('UTC'));
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $seconds = \max(0, $now->getTimestamp() - $available->getTimestamp());

        return (int) \floor($seconds / 60);
    }

    private function shouldExcludeFailedQueues(): bool
    {
        $value = $this->configService->get(self::CONFIG_EXCLUDE_FAILED);

        // Default true when the setting has never been stored.
        return $value === null ? true : (bool) $value;
    }

    /**
     * @return list<string>
     */
    private function parseCsvList(string $value): array
    {
        if (\trim($value) === '') {
            return [];
        }

        $parts = \array_map(\trim(...), \explode(',', $value));

        return \array_values(\array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    /**
     * Parses "async:15, low_priority:60" into queue => grace minutes.
     *
     * @return array<string, int>
     */
    private function parseGraceMap(string $value): array
    {
        $map = [];
        foreach ($this->parseCsvList($value) as $entry) {
            if (!\str_contains($entry, ':')) {
                continue;
            }

            [$name, $minutes] = \array_map(\trim(...), \explode(':', $entry, 2));
            if ($name === '' || !\is_numeric($minutes)) {
                continue;
            }

            $grace = (int) $minutes;
            if ($grace > 0) {
                $map[$name] = $grace;
            }
        }

        return $map;
    }
}
