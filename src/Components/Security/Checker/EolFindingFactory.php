<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Checker;

use Frosh\Tools\Components\Security\SecurityFinding;

/**
 * Turns a resolved endoflife.date cycle into a severity-scored {@see SecurityFinding}.
 */
class EolFindingFactory
{
    /**
     * @param array{cycle: string, eol: \DateTimeImmutable|null, eolUnknown: bool, supportEnded: bool, latest: string|null}|null $cycle
     */
    public static function fromCycle(
        string $id,
        string $title,
        string $currentVersion,
        ?array $cycle,
        string $docUrl,
    ): SecurityFinding {
        if ($cycle === null) {
            return SecurityFinding::unknown(
                $id,
                SecurityFinding::CATEGORY_RUNTIME,
                $title,
                $currentVersion,
                'Could not determine end-of-life status (endoflife.date unreachable or version unknown)',
                $docUrl,
            );
        }

        $now = new \DateTimeImmutable();
        $eol = $cycle['eol'];

        if ($cycle['eolUnknown']) {
            return SecurityFinding::unknown(
                $id,
                SecurityFinding::CATEGORY_RUNTIME,
                $title,
                $currentVersion,
                'End-of-life date is unknown for this release',
                $docUrl,
            );
        }

        if ($eol !== null && $eol < $now) {
            return SecurityFinding::critical(
                $id,
                SecurityFinding::CATEGORY_RUNTIME,
                $title,
                \sprintf('%s (end-of-life since %s)', $currentVersion, $eol->format('Y-m-d')),
                'Upgrade to a supported release that still receives security fixes',
                $docUrl,
            );
        }

        if ($eol !== null && $eol < $now->modify('+3 months')) {
            return SecurityFinding::high(
                $id,
                SecurityFinding::CATEGORY_RUNTIME,
                $title,
                \sprintf('%s (end-of-life on %s, less than 3 months)', $currentVersion, $eol->format('Y-m-d')),
                'Plan an upgrade before this release reaches end-of-life',
                $docUrl,
            );
        }

        if ($cycle['supportEnded']) {
            return SecurityFinding::medium(
                $id,
                SecurityFinding::CATEGORY_RUNTIME,
                $title,
                \sprintf('%s (active support ended, security fixes only)', $currentVersion),
                'Consider upgrading to a release with active support',
                $docUrl,
            );
        }

        $current = $eol !== null
            ? \sprintf('%s (supported until %s)', $currentVersion, $eol->format('Y-m-d'))
            : \sprintf('%s (supported)', $currentVersion);

        return SecurityFinding::ok(
            $id,
            SecurityFinding::CATEGORY_RUNTIME,
            $title,
            $current,
            '',
            $docUrl,
        );
    }
}
