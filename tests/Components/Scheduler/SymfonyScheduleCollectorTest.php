<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Scheduler;

use Frosh\Tools\Components\Scheduler\Struct\RecurringMessageStruct;
use Frosh\Tools\Components\Scheduler\SymfonyScheduleCollector;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\SymfonyBridge\ScheduleProvider;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[CoversClass(SymfonyScheduleCollector::class)]
#[CoversClass(RecurringMessageStruct::class)]
class SymfonyScheduleCollectorTest extends TestCase
{
    public function testCollectReturnsNothingWithoutSchedules(): void
    {
        static::assertSame([], (new SymfonyScheduleCollector(new ServiceLocator([])))->collect());
    }

    public function testCollectUnwrapsRedispatchedCommandMessages(): void
    {
        $schedule = (new Schedule())->add(
            RecurringMessage::cron(
                '35 3 * * *',
                new RedispatchMessage(new RunCommandMessage('app:cleanup:orders'), 'low_priority'),
            ),
        );

        $schedules = (new SymfonyScheduleCollector($this->createLocator('example', $schedule)))->collect();

        static::assertCount(1, $schedules);
        static::assertSame('example', $schedules[0]->name);
        static::assertFalse($schedules[0]->stateful);
        static::assertNull($schedules[0]->checkpoint);
        static::assertNull($schedules[0]->error);

        $messages = $schedules[0]->messages;
        static::assertCount(1, $messages);

        $message = $messages[0];
        static::assertSame('app:cleanup:orders', $message->label);
        static::assertSame(RunCommandMessage::class, $message->messageClass);
        static::assertSame(['low_priority'], $message->transports);
        static::assertSame('35 3 * * *', $message->trigger);
        static::assertSame('cron', $message->triggerType);
        static::assertFalse($message->terminated);
        static::assertNotNull($message->nextRunDate);
        static::assertNotSame('', $message->id);
    }

    public function testCollectDescribesPeriodicTasks(): void
    {
        $schedule = (new Schedule())->add(
            RecurringMessage::every('1 hour', new RunCommandMessage('app:stock:update')),
        );

        $messages = (new SymfonyScheduleCollector($this->createLocator('example', $schedule)))->collect()[0]->messages;

        static::assertCount(1, $messages);
        static::assertSame('periodic', $messages[0]->triggerType);
        static::assertSame('app:stock:update', $messages[0]->label);
        static::assertSame([], $messages[0]->transports);
    }

    public function testCollectSortsTerminatedMessagesLast(): void
    {
        $schedule = (new Schedule())->add(
            RecurringMessage::every('1 hour', new RunCommandMessage('app:terminated'), until: new \DateTimeImmutable('2000-01-01')),
            RecurringMessage::every('1 hour', new RunCommandMessage('app:active')),
        );

        $messages = (new SymfonyScheduleCollector($this->createLocator('example', $schedule)))->collect()[0]->messages;

        static::assertSame(['app:active', 'app:terminated'], array_map(
            static fn (RecurringMessageStruct $message) => $message->label,
            $messages,
        ));
        static::assertTrue($messages[1]->terminated);
    }

    public function testCollectKeepsGoingWhenAProviderFails(): void
    {
        $provider = new class implements ScheduleProviderInterface {
            public function getSchedule(): Schedule
            {
                throw new \RuntimeException('provider is broken');
            }
        };

        $schedules = (new SymfonyScheduleCollector(new ServiceLocator([
            'broken' => static fn () => $provider,
        ])))->collect();

        static::assertCount(1, $schedules);
        static::assertSame('broken', $schedules[0]->name);
        static::assertSame('provider is broken', $schedules[0]->error);
        static::assertSame([], $schedules[0]->messages);
    }

    public function testCollectSkipsTheShopwareScheduledTaskBridge(): void
    {
        if (!class_exists(ScheduleProvider::class)) {
            static::markTestSkipped('The Shopware scheduled task bridge is not available.');
        }

        $provider = new ScheduleProvider(
            [],
            $this->createStub(Connection::class),
            $this->createStub(CacheInterface::class),
            new LockFactory(new InMemoryStore()),
        );

        $schedules = (new SymfonyScheduleCollector(new ServiceLocator([
            'shopware' => static fn () => $provider,
        ])))->collect();

        static::assertSame([], $schedules);
    }

    public function testFindRecurringMessage(): void
    {
        $recurringMessage = RecurringMessage::cron('35 3 * * *', new RunCommandMessage('app:cleanup:orders'));
        $collector = new SymfonyScheduleCollector($this->createLocator('example', (new Schedule())->add($recurringMessage)));

        static::assertSame($recurringMessage, $collector->findRecurringMessage('example', $recurringMessage->getId()));
        static::assertNull($collector->findRecurringMessage('example', 'unknown'));
        static::assertNull($collector->findRecurringMessage('unknown', $recurringMessage->getId()));
    }

    /**
     * @return ServiceLocator<ScheduleProviderInterface>
     */
    private function createLocator(string $name, Schedule $schedule): ServiceLocator
    {
        $provider = new class($schedule) implements ScheduleProviderInterface {
            public function __construct(private readonly Schedule $schedule)
            {
            }

            public function getSchedule(): Schedule
            {
                return $this->schedule;
            }
        };

        return new ServiceLocator([$name => static fn () => $provider]);
    }
}
