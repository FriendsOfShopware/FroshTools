<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\ComposerAudit\ComposerAuditService;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class ComposerAuditChecker implements HealthCheckerInterface, CheckerInterface
{
    private const ID = 'composer-audit';
    private const SNIPPET = 'Composer security advisories';

    public function __construct(
        private readonly ComposerAuditService $composerAuditService,
    ) {}

    public function collect(HealthCollection $collection): void
    {
        try {
            $result = $this->composerAuditService->audit();
        } catch (\Throwable $e) {
            $collection->add(
                SettingsResult::warning(
                    self::ID,
                    self::SNIPPET,
                    \sprintf('audit failed: %s', $e->getMessage()),
                    'audit reachable',
                ),
            );

            return;
        }

        if (isset($result['error']) && $result['error'] !== '') {
            $collection->add(
                SettingsResult::warning(
                    self::ID,
                    self::SNIPPET,
                    $result['error'],
                    'audit reachable',
                ),
            );

            return;
        }

        $advisoryCount = \count($result['advisories']);
        $vulnerable = $result['vulnerable'];
        $total = $result['packages'];

        if ($advisoryCount === 0) {
            $collection->add(
                SettingsResult::ok(
                    self::ID,
                    self::SNIPPET,
                    \sprintf('no advisories (%d packages)', $total),
                ),
            );

            return;
        }

        $hasCritical = false;
        $hasHigh = false;
        foreach ($result['advisories'] as $advisory) {
            $severity = strtolower((string) ($advisory['severity'] ?? ''));
            if ($severity === 'critical') {
                $hasCritical = true;
            }
            if ($severity === 'high') {
                $hasHigh = true;
            }
        }

        $current = \sprintf('%d advisories on %d packages', $advisoryCount, $vulnerable);
        $recommended = 'review and update affected dependencies';

        if ($hasCritical || $hasHigh) {
            $collection->add(
                SettingsResult::error(self::ID, self::SNIPPET, $current, $recommended),
            );

            return;
        }

        $collection->add(
            SettingsResult::warning(self::ID, self::SNIPPET, $current, $recommended),
        );
    }
}
