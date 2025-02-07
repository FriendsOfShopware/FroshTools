<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<SettingsResult>
 */
class HealthCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return SettingsResult::class;
    }

    public function getState(): string
    {
        foreach ([SettingsResult::ERROR, SettingsResult::WARNING] as $state) {
            $healthStatus = $this->filter(function (SettingsResult $result) use ($state) {
                return $result->state === $state;
            });

            if ($healthStatus->count() > 0) {
                return $state;
            }
        }

        return SettingsResult::GREEN;
    }
}
