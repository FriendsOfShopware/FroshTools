<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Subscriber;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Security\Subscriber\SystemActivitySubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

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

    public function testLogsAppLifecycleEvents(): void
    {
        $logs = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(4))->method('info')->willReturnCallback(
            static function (string $message, array $context) use (&$logs): void {
                $logs[] = [$message, $context];
            },
        );
        $subscriber = new SystemActivitySubscriber($logger, static::createStub(Connection::class));
        $context = Context::createDefaultContext();
        $app = new AppEntity();
        $app->setName('ExampleApp');
        $app->setVersion('1.2.3');
        $manifest = Manifest::createFromXml(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                <meta>
                    <name>ExampleApp</name>
                    <label>Example App</label>
                    <description>Example</description>
                    <author>Example</author>
                    <copyright>Example</copyright>
                    <version>1.2.3</version>
                    <license>MIT</license>
                </meta>
            </manifest>
            XML);

        $subscriber->onAppLifecycle(new AppActivatedEvent($app, $context));
        $subscriber->onAppLifecycle(new AppDeactivatedEvent($app, $context));
        $subscriber->onAppLifecycle(new AppInstalledEvent($app, $manifest, $context));
        $subscriber->onAppLifecycle(new AppUpdatedEvent($app, $manifest, $context));

        static::assertSame([
            ['app:enable', ['appName' => 'ExampleApp', 'appVersion' => '1.2.3', 'actorType' => 'system']],
            ['app:disable', ['appName' => 'ExampleApp', 'appVersion' => '1.2.3', 'actorType' => 'system']],
            ['app:install', ['appName' => 'ExampleApp', 'appVersion' => '1.2.3', 'actorType' => 'system']],
            ['app:update', ['appName' => 'ExampleApp', 'appVersion' => '1.2.3', 'actorType' => 'system']],
        ], $logs);
    }

    public function testLogsAppDeleteFromRemainingRow(): void
    {
        $appId = Uuid::randomHex();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('app:delete', [
            'appName' => 'ExampleApp',
            'appVersion' => '1.2.3',
            'actorType' => 'system',
        ]);
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAssociative')->willReturn([
            'name' => 'ExampleApp',
            'version' => '1.2.3',
        ]);
        $subscriber = new SystemActivitySubscriber($logger, $connection);

        $subscriber->onAppDeleted(new AppDeletedEvent($appId, Context::createDefaultContext()));
    }

    public function testLogsAppIdWhenDeletedAppRowIsMissing(): void
    {
        $appId = Uuid::randomHex();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('app:delete', [
            'appId' => $appId,
            'actorType' => 'system',
        ]);
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);
        $subscriber = new SystemActivitySubscriber($logger, $connection);

        $subscriber->onAppDeleted(new AppDeletedEvent($appId, Context::createDefaultContext()));
    }
}
