<?php

declare(strict_types=1);

namespace Frosh\Tools\Task;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class RedisPrefixCleanupTask extends ScheduledTask
{
    final public const NAME = 'frosh.tools.redis_prefix_cleanup';

    public static function getTaskName(): string
    {
        return self::NAME;
    }

    public static function getDefaultInterval(): int
    {
        return 86400;
    }
}
