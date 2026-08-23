<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Components\Scheduler\Struct\RecurringMessageStruct;
use Frosh\Tools\Components\Scheduler\Struct\ScheduleStruct;
use Frosh\Tools\Components\Scheduler\SymfonyScheduleCollector;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Detects that nobody consumes a symfony/scheduler transport (`messenger:consume scheduler_<name>`).
 *
 * A schedule's checkpoint only moves forward when a message is actually dispatched, so its plain
 * age says nothing: a weekly task legitimately leaves the checkpoint a week behind. What does say
 * something is the first message due *after* that checkpoint — once that moment has passed by more
 * than the grace time and the checkpoint still has not moved, no worker picked the message up.
 */
class SymfonySchedulerChecker implements HealthCheckerInterface, CheckerInterface
{
    private const CONFIG_GRACE = 'FroshTools.config.monitorTaskGraceTime';
    private const DEFAULT_GRACE_MINUTES = 10;
    private const SNIPPET = 'Symfony scheduler overdue';

    public function __construct(
        private readonly SymfonyScheduleCollector $collector,
        private readonly SystemConfigService $configService,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $schedules = $this->collector->collect();

        // Without a single schedule the component is unused here, so a permanently green row
        // would only add noise to the health list.
        if ($schedules === []) {
            return;
        }

        $grace = $this->configService->getInt(self::CONFIG_GRACE) ?: self::DEFAULT_GRACE_MINUTES;
        $recommended = \sprintf('max %d mins', $grace);
        $now = new \DateTimeImmutable();

        $evaluated = 0;
        $neverRun = [];
        $worstName = null;
        $worstMinutes = 0;

        foreach ($schedules as $schedule) {
            // A schedule that failed to build, or that keeps no state, cannot tell us whether a
            // worker consumes it.
            if ($schedule->error !== null || !$schedule->stateful) {
                continue;
            }

            ++$evaluated;

            $checkpoint = $schedule->checkpoint;
            if ($checkpoint === null) {
                $neverRun[] = $schedule->name;
                continue;
            }

            $dueAt = $this->firstDueDate($schedule);
            if ($dueAt === null || $dueAt > $now) {
                continue;
            }

            $overdueMinutes = (int) \floor(($now->getTimestamp() - $dueAt->getTimestamp()) / 60);
            if ($overdueMinutes > $worstMinutes) {
                $worstMinutes = $overdueMinutes;
                $worstName = $schedule->name;
            }
        }

        if ($evaluated === 0) {
            $collection->add(SettingsResult::info('symfony_scheduler', self::SNIPPET, 'not monitored', $recommended));

            return;
        }

        if ($neverRun !== []) {
            $collection->add(SettingsResult::warning(
                'symfony_scheduler',
                self::SNIPPET,
                \sprintf('never consumed (%s)', implode(', ', $neverRun)),
                $recommended,
            ));

            return;
        }

        if ($worstName !== null && $worstMinutes > $grace) {
            $collection->add(SettingsResult::warning(
                'symfony_scheduler',
                self::SNIPPET,
                \sprintf('%d mins (%s)', $worstMinutes, $worstName),
                $recommended,
            ));

            return;
        }

        $collection->add(SettingsResult::ok('symfony_scheduler', self::SNIPPET, \sprintf('%d mins', $worstMinutes), $recommended));
    }

    /**
     * The earliest moment a message of this schedule is due, counted from the schedule's
     * checkpoint - which is exactly what the collector computes the next run dates from.
     */
    private function firstDueDate(ScheduleStruct $schedule): ?\DateTimeImmutable
    {
        $dates = array_filter(array_map(
            static fn (RecurringMessageStruct $message) => $message->nextRunDate,
            $schedule->messages,
        ));

        if ($dates === []) {
            return null;
        }

        return min($dates);
    }
}
