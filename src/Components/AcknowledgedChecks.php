<?php

declare(strict_types=1);

namespace Frosh\Tools\Components;

use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;

final class AcknowledgedChecks
{
    /**
     * Downgrade configured checks to info after they were collected and before
     * disabled checks are removed. An id present in both lists is therefore hidden.
     *
     * @param array<string, mixed> $acknowledgedChecks
     */
    public static function apply(HealthCollection|SecurityCollection $collection, array $acknowledgedChecks): void
    {
        if ($acknowledgedChecks === []) {
            return;
        }

        foreach ($collection as $result) {
            $reason = self::reasonFor($acknowledgedChecks, $result->id);
            if ($reason === null) {
                continue;
            }

            if ($result instanceof SettingsResult) {
                $result->state = SettingsResult::INFO;
                $result->acknowledgement = $reason;
                continue;
            }

            $result->severity = SecurityFinding::SEVERITY_INFO;
            $result->acknowledgement = $reason;
        }
    }

    /**
     * @param array<string, mixed> $acknowledgedChecks
     */
    private static function reasonFor(array $acknowledgedChecks, string $id): ?string
    {
        $reason = $acknowledgedChecks[$id] ?? null;
        if (!\is_string($reason)) {
            return null;
        }

        $reason = trim($reason);

        return $reason === '' ? null : $reason;
    }
}
