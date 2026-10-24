<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Telemetry\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class TelemetryTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'frosh_tools.telemetry';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
