<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Queue;

use Frosh\Tools\Components\Queue\QueueMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;

#[CoversClass(QueueMessage::class)]
class QueueMessageTest extends TestCase
{
    public function testOnlyPublicMessagePropertiesAreExposed(): void
    {
        $queueMessage = QueueMessage::fromEnvelope(new Envelope(new MessageWithSensitiveState()));

        static::assertSame([
            'visible' => 'safe value',
        ], $queueMessage->body);
    }
}

class MessageWithSensitiveState
{
    public string $visible = 'safe value';

    protected string $token = 'protected token';

    private string $secret = 'private secret';
}
