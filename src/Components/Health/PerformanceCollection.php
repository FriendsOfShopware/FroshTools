<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health;

class PerformanceCollection extends HealthCollection
{
    protected function getExpectedClass(): ?string
    {
        return SettingsResult::class;
    }
}
