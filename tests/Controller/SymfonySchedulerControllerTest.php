<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Components\Scheduler\Struct\ScheduleStruct;
use Frosh\Tools\Components\Scheduler\SymfonyScheduleCollector;
use Frosh\Tools\Controller\SymfonySchedulerController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[CoversClass(SymfonySchedulerController::class)]
class SymfonySchedulerControllerTest extends TestCase
{
    public function testListReturnsTheCollectedSchedules(): void
    {
        $collector = $this->createStub(SymfonyScheduleCollector::class);
        $collector->method('collect')->willReturn([new ScheduleStruct('example')]);

        $response = (new SymfonySchedulerController($collector, $this->createStub(MessageBusInterface::class)))->list();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertStringContainsString('"name":"example"', (string) $response->getContent());
    }

    public function testRunTaskReturnsNotFoundForAnUnknownMessage(): void
    {
        $collector = $this->createStub(SymfonyScheduleCollector::class);
        $collector->method('findRecurringMessage')->willReturn(null);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(static::never())->method('dispatch');

        $response = (new SymfonySchedulerController($collector, $bus))->runTask('example', 'unknown');

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testRunTaskDispatchesAlreadyRedispatchedMessagesUnchanged(): void
    {
        $message = new RedispatchMessage(new RunCommandMessage('app:cleanup:orders'), 'low_priority');
        $dispatched = $this->dispatchViaController($message);

        static::assertSame($message, $dispatched);
    }

    public function testRunTaskWrapsUnroutedMessagesToAvoidSynchronousExecution(): void
    {
        $dispatched = $this->dispatchViaController(new RunCommandMessage('app:cleanup:orders'));

        static::assertInstanceOf(RedispatchMessage::class, $dispatched);
        static::assertSame('async', $dispatched->transportNames);
        static::assertInstanceOf(RunCommandMessage::class, $dispatched->envelope);
    }

    private function dispatchViaController(object $message): object
    {
        $recurringMessage = RecurringMessage::cron('35 3 * * *', $message);

        $collector = new SymfonyScheduleCollector($this->createLocator('example', (new Schedule())->add($recurringMessage)));

        $dispatched = null;
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(static::once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $dispatchedMessage) use (&$dispatched): Envelope {
                $dispatched = $dispatchedMessage;

                return new Envelope($dispatchedMessage);
            });

        $response = (new SymfonySchedulerController($collector, $bus))->runTask('example', $recurringMessage->getId());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertNotNull($dispatched);

        return $dispatched;
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
