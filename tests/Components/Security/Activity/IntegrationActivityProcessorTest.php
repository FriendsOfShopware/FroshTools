<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Activity;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Security\Activity\ActivityHandler;
use Frosh\Tools\Components\Security\Activity\ActivityStore;
use Frosh\Tools\Components\Security\Activity\IntegrationActivityProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\RequestStack;

/** @internal */
#[Package('framework')]
#[CoversClass(IntegrationActivityProcessor::class)]
class IntegrationActivityProcessorTest extends TestCase
{
    #[DataProvider('integrationEvents')]
    public function testSnapshotsIntegrationName(string $action, ?string $suppliedName, mixed $lookupResult): void
    {
        $id = str_repeat('a', 32);
        $connection = $this->createMock(Connection::class);
        $connection->expects($suppliedName === null ? $this->once() : $this->never())->method('fetchOne')
            ->with('SELECT label FROM integration WHERE id = :id', ['id' => hex2bin($id)])
            ->willReturn($lookupResult);
        $context = ['entityId' => $id];
        if ($suppliedName !== null) {
            $context['integrationName'] = $suppliedName;
        }
        $expected = $context;
        if ($suppliedName === null && \is_string($lookupResult)) {
            $expected['integrationName'] = $lookupResult;
        }
        $store = $this->createMock(ActivityStore::class);
        $store->expects($this->once())->method('append')->with($action, 200, $expected, $this->isInstanceOf(\DateTimeImmutable::class));
        $handler = new ActivityHandler($store, new NullLogger(), new RequestStack());
        $processor = new IntegrationActivityProcessor($connection);
        $handler->handle($processor(new LogRecord(new \DateTimeImmutable(), 'system_activity', Level::Info, $action, $context)));
    }

    public static function integrationEvents(): iterable
    {
        yield 'core creation' => ['integration:create', null, 'Warehouse'];
        yield 'partial update' => ['integration:update', null, 'Warehouse'];
        yield 'rename preserves supplied snapshot' => ['integration:update', 'New name', 'Later name'];
        yield 'deleted target retains ID' => ['integration:delete', null, false];
    }

    public function testIntegrationLookupFailureStillRecordsEvent(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willThrowException(new \RuntimeException('Unavailable'));
        $context = ['entityId' => str_repeat('a', 32)];
        $store = $this->createMock(ActivityStore::class);
        $store->expects($this->once())->method('append')->with('integration:create', 200, $context, $this->isInstanceOf(\DateTimeImmutable::class));
        $handler = new ActivityHandler($store, new NullLogger(), new RequestStack());
        $processor = new IntegrationActivityProcessor($connection);
        $handler->handle($processor(new LogRecord(new \DateTimeImmutable(), 'system_activity', Level::Info, 'integration:create', $context)));
    }

}
