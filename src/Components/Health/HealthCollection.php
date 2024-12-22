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
}
