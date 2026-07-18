<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Queue;

use Frosh\Tools\Components\Queue\QueueMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Message\GenerateThumbnailsMessage;
use Symfony\Component\Messenger\Envelope;

#[CoversClass(QueueMessage::class)]
class QueueMessageTest extends TestCase
{
    public function testPrivateAndProtectedMessagePropertiesAreExposed(): void
    {
        $queueMessage = QueueMessage::fromEnvelope(new Envelope(new MessageWithSensitiveState()));

        static::assertSame([
            'visible' => 'safe value',
            'token' => 'protected token',
            'secret' => 'private secret',
        ], $queueMessage->body);
    }

    public function testGenerateThumbnailsMessageExposesItsPrivateMediaIds(): void
    {
        $message = new GenerateThumbnailsMessage();
        $message->setMediaIds(['media-id']);

        $queueMessage = QueueMessage::fromEnvelope(new Envelope($message));

        static::assertSame(['media-id'], $queueMessage->body['mediaIds']);
    }
}

class MessageWithSensitiveState
{
    public string $visible = 'safe value';

    protected string $token = 'protected token';

    private string $secret = 'private secret';
}
