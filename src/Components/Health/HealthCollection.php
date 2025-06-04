<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<SettingsResult>
 */
class HealthCollection extends Collection
{
    private const STATE_PRIORITY = [
        SettingsResult::ERROR => 0,
        SettingsResult::WARNING => 1,
        SettingsResult::INFO => 2,
        SettingsResult::GREEN => 3,
    ];

    public function sortByState(): void
    {
        $this->sort(static function (SettingsResult $a, SettingsResult $b) {
            $statePriorityA = self::STATE_PRIORITY[$a->state] ?? -1;
            $statePriorityB = self::STATE_PRIORITY[$b->state] ?? -1;
            
            // If states are different, sort by state priority
            if ($statePriorityA !== $statePriorityB) {
                return $statePriorityA <=> $statePriorityB;
            }
            
            // If states are the same, sort alphabetically by snippet
            return $a->snippet <=> $b->snippet;
        });
    }

    protected function getExpectedClass(): ?string
    {
        return SettingsResult::class;
    }
}
