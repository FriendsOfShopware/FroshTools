<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Queue;

use Frosh\Tools\Components\Queue\ListableQueueAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;

#[CoversClass(ListableQueueAdapter::class)]
class ListableQueueAdapterTest extends TestCase
{
    public function testRetryRemovesFailureCopyBeforeDispatching(): void
    {
        $calls = [];
        $failedEnvelope = new Envelope(new \stdClass(), [new SentToFailureTransportStamp('async')]);

        $transport = $this->createMock(ListableReceiverInterface::class);
        $transport->method('find')->with('42')->willReturn($failedEnvelope);
        $transport->method('reject')->willReturnCallback(static function (Envelope $envelope) use (&$calls, $failedEnvelope): void {
            static::assertSame($failedEnvelope, $envelope);
            $calls[] = 'reject';
        });

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (Envelope $envelope) use (&$calls): Envelope {
            $calls[] = 'dispatch';
            static::assertSame(['async'], $envelope->last(TransportNamesStamp::class)?->getTransportNames());

            return $envelope;
        });

        (new ListableQueueAdapter('failed', $transport, $bus))->retryMessage('42');

        static::assertSame(['reject', 'dispatch'], $calls);
    }

    public function testRetryDoesNotDispatchWhenRemovingFailureCopyFails(): void
    {
        $failedEnvelope = new Envelope(new \stdClass(), [new SentToFailureTransportStamp('async')]);
        $error = new \RuntimeException('Message was consumed concurrently');

        $transport = $this->createMock(ListableReceiverInterface::class);
        $transport->method('find')->with('42')->willReturn($failedEnvelope);
        $transport->method('reject')->willThrowException($error);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $this->expectExceptionObject($error);

        (new ListableQueueAdapter('failed', $transport, $bus))->retryMessage('42');
    }

    public function testPurgeContinuesAfterConcurrentRemoval(): void
    {
        $firstBatch = array_map(static fn (int $index) => new Envelope((object) ['id' => $index]), range(1, 100));
        $remainingMessage = (object) ['id' => 101];
        $remainingEnvelope = new Envelope($remainingMessage);
        $error = new \RuntimeException('Message was consumed concurrently');
        $rejectedMessages = [];

        $transport = $this->createMock(ListableReceiverInterface::class);
        $transport->method('all')->willReturnOnConsecutiveCalls($firstBatch, [$remainingEnvelope]);
        $transport->method('reject')->willReturnCallback(static function (Envelope $envelope) use (&$rejectedMessages, $firstBatch, $error): void {
            if ($envelope === $firstBatch[0]) {
                throw $error;
            }

            $rejectedMessages[] = $envelope->getMessage();
        });

        $this->expectExceptionObject($error);

        try {
            (new ListableQueueAdapter('async', $transport, static::createStub(MessageBusInterface::class)))->purge();
        } finally {
            static::assertTrue(\in_array($remainingMessage, $rejectedMessages, true));
            static::assertCount(100, $rejectedMessages);
        }
    }
}
