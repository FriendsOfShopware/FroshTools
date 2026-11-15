<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Activity;

use Frosh\Tools\Components\Security\Activity\ActivityCleanupTask;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActivityCleanupTask::class)]
class ActivityCleanupTaskTest extends TestCase
{
    public function testCleanupRunsDaily(): void
    {
        static::assertSame(86400, ActivityCleanupTask::getDefaultInterval());
        static::assertSame('frosh_tools.security_activity.cleanup', ActivityCleanupTask::getTaskName());
    }
}
