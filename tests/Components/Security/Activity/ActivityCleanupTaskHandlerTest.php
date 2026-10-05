<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Activity;

use Frosh\Tools\Components\Security\Activity\ActivityCleanupTaskHandler;
use Frosh\Tools\Components\Security\Activity\ActivityStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ActivityCleanupTaskHandler::class)]
class ActivityCleanupTaskHandlerTest extends TestCase
{
    public function testDeletesOnlyEventsBeforeConfiguredRetention(): void
    {
        $store = $this->createMock(ActivityStore::class);
        $store->expects($this->once())->method('deleteBefore')->with(new \DateTimeImmutable('2026-09-04 UTC'));
        $handler = new ActivityCleanupTaskHandler(new StaticEntityRepository([new ScheduledTaskCollection()]), new NullLogger(), $store, new MockClock('2026-10-04 UTC'), 30);
        $handler->run();
    }
}
