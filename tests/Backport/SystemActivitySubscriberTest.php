<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Backport;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Backport\SystemActivitySubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemActivitySubscriber::class)]
final class SystemActivitySubscriberTest extends TestCase
{
    public function testLogsOnlyNewUsers(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('user:create', [
            'entityId' => 'new-user',
            'actorType' => 'system',
        ]);
        $subscriber = new SystemActivitySubscriber($logger, static::createStub(Connection::class));
        $event = new EntityWrittenEvent('user', [
            new EntityWriteResult('new-user', ['password' => 'secret'], 'user', EntityWriteResult::OPERATION_INSERT),
            new EntityWriteResult('existing-user', [], 'user', EntityWriteResult::OPERATION_UPDATE),
        ], Context::createDefaultContext());

        $subscriber->onEntityWritten($event);
    }
}
