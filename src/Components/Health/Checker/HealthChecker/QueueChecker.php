<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Doctrine\DBAL\ArrayParameterType;
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
        $pendingByQueue = $this->fetchOldestPendingMessagePerQueue($excludeFailed, $queues);

        if ($pendingByQueue === []) {
            $collection->add(SettingsResult::info(
                'queue',
                $snippet,
                '0 mins',
                \sprintf('max %d mins', $defaultGrace),
            ));

            return;
        }

        $worst = $this->selectWorstQueue($pendingByQueue, $graceByQueue, $defaultGrace);
        $recommended = \sprintf('max %d mins', $worst['grace']);
        $current = \sprintf('%d mins (%s)', $worst['ageMinutes'], $worst['queueName']);

        if ($worst['overdue']) {
            $collection->add(SettingsResult::warning('queue', $snippet, $current, $recommended));

            return;
        }

        $collection->add(SettingsResult::ok('queue', $snippet, $current, $recommended));
    }

    /**
     * @param list<string> $queues
     *
     * @return list<array{available_at: string, queue_name: string}>
     */
    private function fetchOldestPendingMessagePerQueue(bool $excludeFailed, array $queues): array
    {
        // One row per queue (oldest pending message). Evaluating each queue against its
        // own grace avoids masking a tighter queue behind an older message on a looser one.
        $query = $this->connection->createQueryBuilder()
            ->select('queue_name', 'MIN(available_at) AS available_at')
            ->from('messenger_messages')
            ->where('available_at <= UTC_TIMESTAMP()')
            ->groupBy('queue_name')
            ->orderBy('available_at', 'ASC');

        if ($excludeFailed) {
            // Symfony failure transport names typically contain "failed" (e.g. async_failed).
            $query
                ->andWhere('queue_name NOT LIKE :failedPattern')
                ->setParameter('failedPattern', '%failed%');
        }

        if ($queues !== []) {
            $query
                ->andWhere('queue_name IN (:queues)')
                ->setParameter('queues', $queues, ArrayParameterType::STRING);
        }

        /** @var list<array{available_at: string, queue_name: string}> $rows */
        $rows = $query->fetchAllAssociative();

        return $rows;
    }

    /**
     * Prefer any overdue queue (highest minutes-over-grace); otherwise the oldest pending age.
     *
     * @param list<array{available_at: string, queue_name: string}> $pendingByQueue
     * @param array<string, int> $graceByQueue
     *
     * @return array{queueName: string, ageMinutes: int, grace: int, overdue: bool}
     */
    private function selectWorstQueue(array $pendingByQueue, array $graceByQueue, int $defaultGrace): array
    {
        $worst = null;

        foreach ($pendingByQueue as $row) {
            $queueName = (string) $row['queue_name'];
            $grace = $graceByQueue[$queueName] ?? $defaultGrace;
            $ageMinutes = $this->ageInMinutes((string) $row['available_at']);
            $overdue = $ageMinutes > $grace;
            $overBy = $overdue ? $ageMinutes - $grace : 0;

            $candidate = [
                'queueName' => $queueName,
                'ageMinutes' => $ageMinutes,
                'grace' => $grace,
                'overdue' => $overdue,
                'overBy' => $overBy,
            ];

            if ($worst === null) {
                $worst = $candidate;
                continue;
            }

            // Overdue always beats healthy.
            if ($candidate['overdue'] !== $worst['overdue']) {
                if ($candidate['overdue']) {
                    $worst = $candidate;
                }
                continue;
            }

            // Same state: both overdue → furthest past grace; both healthy → oldest age.
            if ($candidate['overdue']) {
                if ($candidate['overBy'] > $worst['overBy']
                    || ($candidate['overBy'] === $worst['overBy'] && $candidate['ageMinutes'] > $worst['ageMinutes'])) {
                    $worst = $candidate;
                }
                continue;
            }

            if ($candidate['ageMinutes'] > $worst['ageMinutes']) {
                $worst = $candidate;
            }
        }

        \assert($worst !== null);

        return [
            'queueName' => $worst['queueName'],
            'ageMinutes' => $worst['ageMinutes'],
            'grace' => $worst['grace'],
            'overdue' => $worst['overdue'],
        ];
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
