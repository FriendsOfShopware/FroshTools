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
            return (self::STATE_PRIORITY[$a->state] ?? -1) <=> (self::STATE_PRIORITY[$b->state] ?? -1);
        });
    }

    /**
     * @param array<string> $ids
     */
    public function removeByIds(array $ids): void
    {
        /** @phpstan-ignore-next-line */
        $this->elements = array_filter($this->elements, static function (SettingsResult $result) use ($ids) {
            return !\in_array($result->id, $ids, true);
        });
    }

    protected function getExpectedClass(): ?string
    {
        return SettingsResult::class;
    }
}
