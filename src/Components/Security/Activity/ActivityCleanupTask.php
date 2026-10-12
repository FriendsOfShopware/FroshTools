<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Activity;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 */
class ActivityCleanupTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'frosh_tools.security_activity.cleanup';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }
}
