<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Checker;

use Frosh\Tools\Components\ComposerAudit\ComposerAuditService;
use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;

class DependencyAuditChecker implements SecurityCheckerInterface
{
    public function __construct(
        private readonly ComposerAuditService $composerAuditService,
    ) {}

    public function collect(SecurityCollection $collection): void
    {
        try {
            $result = $this->composerAuditService->audit();
        } catch (\Throwable $e) {
            $collection->add(SecurityFinding::unknown(
                'composer-audit',
                SecurityFinding::CATEGORY_DEPENDENCIES,
                'Composer security advisories',
                \sprintf('audit failed: %s', $e->getMessage()),
                'Make sure the shop can reach packagist.org',
            ));

            return;
        }

        if (isset($result['error']) && $result['error'] !== '') {
            $collection->add(SecurityFinding::unknown(
                'composer-audit',
                SecurityFinding::CATEGORY_DEPENDENCIES,
                'Composer security advisories',
                $result['error'],
                'Make sure the shop can reach packagist.org',
            ));

            return;
        }

        $advisories = $result['advisories'];

        if ($advisories === []) {
            $collection->add(SecurityFinding::ok(
                'composer-audit',
                SecurityFinding::CATEGORY_DEPENDENCIES,
                'Composer security advisories',
                \sprintf('no advisories (%d packages checked)', $result['packages']),
            ));

            return;
        }

        foreach ($advisories as $advisory) {
            $packageName = (string) ($advisory['packageName'] ?? '');
            $installedVersion = isset($advisory['installedVersion']) ? (string) $advisory['installedVersion'] : '';
            $reference = $advisory['cve'] ?? $advisory['advisoryId'] ?? null;

            $current = $installedVersion !== ''
                ? \sprintf('%s %s', $packageName, $installedVersion)
                : $packageName;

            $title = (string) ($advisory['title'] ?? '');
            if ($reference !== null && $reference !== '') {
                $title = \sprintf('%s (%s)', $title, $reference);
            }

            $collection->add(new SecurityFinding(
                'composer-audit-' . md5($packageName . '|' . $installedVersion . '|' . (string) ($advisory['advisoryId'] ?? $title)),
                SecurityFinding::CATEGORY_DEPENDENCIES,
                $this->mapSeverity((string) ($advisory['severity'] ?? '')),
                $title !== '' ? $title : 'Security advisory',
                $current,
                'Update the affected dependency to a patched version',
                isset($advisory['link']) ? (string) $advisory['link'] : null,
            ));
        }
    }

    private function mapSeverity(string $severity): string
    {
        return match (strtolower($severity)) {
            'critical' => SecurityFinding::SEVERITY_CRITICAL,
            'high' => SecurityFinding::SEVERITY_HIGH,
            'medium', 'moderate' => SecurityFinding::SEVERITY_MEDIUM,
            'low' => SecurityFinding::SEVERITY_LOW,
            default => SecurityFinding::SEVERITY_MEDIUM,
        };
    }
}
