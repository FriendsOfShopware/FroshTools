<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\SettingsResult;

final class DataIntegrityCheckResult
{
    public static function fromCount(string $id, string $snippet, int $count): SettingsResult
    {
        if ($count === 0) {
            return SettingsResult::ok($id, $snippet, '0', '0');
        }

        return SettingsResult::info($id, $snippet, (string) $count, '0');
    }
}
