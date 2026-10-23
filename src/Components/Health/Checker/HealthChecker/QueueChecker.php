<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Components\Queue\DoctrineQueueAdapter;
use Frosh\Tools\Components\Queue\QueueRegistry;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class QueueChecker implements HealthCheckerInterface, CheckerInterface
{
    private const CONFIG_GRACE = 'FroshTools.config.monitorQueueGraceTime';
    private const CONFIG_EXCLUDE_FAILED = 'FroshTools.config.monitorExcludeFailedQueues';
    private const CONFIG_QUEUES = 'FroshTools.config.monitorQueues';
    private const CONFIG_GRACE_TIMES = 'FroshTools.config.monitorQueueGraceTimes';
    private const DEFAULT_GRACE_MINUTES = 15;

    public function __construct(
        private readonly QueueRegistry $queueRegistry,
        private readonly SystemConfigService $configService,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $defaultGrace = $this->configService->getInt(self::CONFIG_GRACE);
        if ($defaultGrace === 0) {
            $defaultGrace = self::DEFAULT_GRACE_MINUTES;
        }
        $excludeFailed = $this->shouldExcludeFailedQueues();
        $queues = $this->parseCsvList($this->configService->getString(self::CONFIG_QUEUES));
        $graceByQueue = $this->parseGraceMap($this->configService->getString(self::CONFIG_GRACE_TIMES));

        $snippet = 'Open Queues';
        $pendingByQueue = [];
        $unknownQueues = [];

        foreach ($this->queueRegistry->all() as $name => $adapter) {
            // Preserve saved Doctrine queue filters and grace periods from the SQL-based checker.
            if ($adapter instanceof DoctrineQueueAdapter) {
                $name = $adapter->getQueueName();
            }

            if (($excludeFailed && \str_contains(\strtolower($name), 'failed'))
                || ($queues !== [] && !\in_array($name, $queues, true))) {
                continue;
            }

            if ($adapter->getMessageCount() === 0) {
                continue;
            }

            $age = $adapter->getOldestMessageAge();
            if ($age === null) {
                $unknownQueues[] = $name;
                continue;
            }

            $pendingByQueue[] = ['queue_name' => $name, 'ageMinutes' => (int) \floor(\max(0, $age) / 60)];
        }

        $unknown = $unknownQueues === [] ? '' : \sprintf('Age unavailable (%s)', \implode(', ', $unknownQueues));

        if ($pendingByQueue === []) {
            $collection->add(SettingsResult::info(
                'queue',
                $snippet,
                $unknown !== '' ? $unknown : '0 mins',
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

        if ($unknown !== '') {
            $collection->add(SettingsResult::info('queue', $snippet, $current . '; ' . $unknown, $recommended));

            return;
        }

        $collection->add(SettingsResult::ok('queue', $snippet, $current, $recommended));
    }

    /**
     * Prefer any overdue queue (highest minutes-over-grace); otherwise the oldest pending age.
     *
     * @param list<array{queue_name: string, ageMinutes: int}> $pendingByQueue
     * @param array<string, int> $graceByQueue
     *
     * @return array{queueName: string, ageMinutes: int, grace: int, overdue: bool}
     */
    private function selectWorstQueue(array $pendingByQueue, array $graceByQueue, int $defaultGrace): array
    {
        $worst = null;

        foreach ($pendingByQueue as $row) {
            $queueName = $row['queue_name'];
            $grace = $graceByQueue[$queueName] ?? $defaultGrace;
            $ageMinutes = $row['ageMinutes'];
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
