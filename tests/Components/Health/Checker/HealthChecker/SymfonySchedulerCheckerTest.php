<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\HealthChecker\SymfonySchedulerChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Components\Scheduler\Struct\RecurringMessageStruct;
use Frosh\Tools\Components\Scheduler\Struct\ScheduleStruct;
use Frosh\Tools\Components\Scheduler\SymfonyScheduleCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(SymfonySchedulerChecker::class)]
class SymfonySchedulerCheckerTest extends TestCase
{
    public function testNoScheduleAddsNoResult(): void
    {
        $collection = $this->collect([]);

        static::assertCount(0, $collection);
    }

    public function testDueMessageWithinGraceIsOk(): void
    {
        $result = $this->collectOne([
            $this->schedule(checkpoint: '-2 hours', dueDates: ['-5 minutes']),
        ]);

        static::assertSame(SettingsResult::GREEN, $result->state);
        static::assertSame('5 mins', $result->current);
        static::assertSame('max 10 mins', $result->recommended);
    }

    public function testOverdueMessageIsAWarning(): void
    {
        $result = $this->collectOne([
            $this->schedule(name: 'example', checkpoint: '-2 hours', dueDates: ['-45 minutes']),
        ]);

        static::assertSame(SettingsResult::WARNING, $result->state);
        static::assertSame('45 mins (example)', $result->current);
    }

    public function testFutureMessageIsOkEvenWithAVeryOldCheckpoint(): void
    {
        // A weekly task legitimately leaves the checkpoint days behind: what counts is that
        // the next message is not due yet.
        $result = $this->collectOne([
            $this->schedule(checkpoint: '-6 days', dueDates: ['+1 day']),
        ]);

        static::assertSame(SettingsResult::GREEN, $result->state);
        static::assertSame('0 mins', $result->current);
    }

    public function testTerminatedMessagesAreIgnored(): void
    {
        $result = $this->collectOne([
            $this->schedule(checkpoint: '-6 days', dueDates: [null]),
        ]);

        static::assertSame(SettingsResult::GREEN, $result->state);
    }

    public function testMissingCheckpointMeansNeverConsumed(): void
    {
        $result = $this->collectOne([
            $this->schedule(name: 'example', checkpoint: null, dueDates: ['+1 day']),
        ]);

        static::assertSame(SettingsResult::INFO, $result->state);
        static::assertSame('never consumed (example)', $result->current);
    }

    public function testStatelessAndFailedSchedulesCannotBeMonitored(): void
    {
        $result = $this->collectOne([
            new ScheduleStruct('stateless', false, null, []),
            new ScheduleStruct('broken', true, null, [], 'boom'),
        ]);

        static::assertSame(SettingsResult::INFO, $result->state);
        static::assertSame('nothing to monitor', $result->current);
    }

    public function testTheWorstScheduleIsReported(): void
    {
        $result = $this->collectOne([
            $this->schedule(name: 'small', checkpoint: '-2 hours', dueDates: ['-20 minutes']),
            $this->schedule(name: 'big', checkpoint: '-2 hours', dueDates: ['-90 minutes', '-30 minutes']),
        ]);

        static::assertSame(SettingsResult::WARNING, $result->state);
        static::assertSame('90 mins (big)', $result->current);
    }

    public function testConfiguredGraceTimeIsUsed(): void
    {
        $result = $this->collectOne(
            [$this->schedule(name: 'example', checkpoint: '-2 hours', dueDates: ['-45 minutes'])],
            grace: 60,
        );

        static::assertSame(SettingsResult::GREEN, $result->state);
        static::assertSame('max 60 mins', $result->recommended);
    }

    /**
     * @param list<string|null> $dueDates
     */
    private function schedule(string $name = 'example', ?string $checkpoint = '-1 hour', array $dueDates = []): ScheduleStruct
    {
        $messages = array_map(
            static fn (?string $due, int $index) => new RecurringMessageStruct(
                id: 'id-' . $index,
                scheduleName: $name,
                nextRunDate: $due === null ? null : new \DateTimeImmutable($due),
                terminated: $due === null,
            ),
            $dueDates,
            array_keys($dueDates),
        );

        return new ScheduleStruct(
            $name,
            true,
            $checkpoint === null ? null : new \DateTimeImmutable($checkpoint),
            $messages,
        );
    }

    /**
     * @param ScheduleStruct[] $schedules
     */
    private function collectOne(array $schedules, int $grace = 0): SettingsResult
    {
        $result = $this->collect($schedules, $grace)->first();

        static::assertInstanceOf(SettingsResult::class, $result);
        static::assertSame('symfony_scheduler', $result->id);

        return $result;
    }

    /**
     * @param ScheduleStruct[] $schedules
     */
    private function collect(array $schedules, int $grace = 0): HealthCollection
    {
        $collector = $this->createStub(SymfonyScheduleCollector::class);
        $collector->method('collect')->willReturn($schedules);

        $configService = $this->createStub(SystemConfigService::class);
        $configService->method('getInt')->willReturn($grace);

        $collection = new HealthCollection();
        (new SymfonySchedulerChecker($collector, $configService))->collect($collection);

        return $collection;
    }
}
