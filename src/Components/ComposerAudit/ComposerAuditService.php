<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\ComposerAudit;

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ComposerAuditService
{
    public const CACHE_KEY = 'frosh-tools-composer-audit';
    public const CACHE_TTL_SECONDS = 3600;

    private const PACKAGIST_AUDIT_URL = 'https://packagist.org/api/security-advisories/';
    private const CHUNK_SIZE = 20;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cacheObject,
    ) {
    }

    /**
     * @return array{packages: int, vulnerable: int, advisories: list<array<string, mixed>>, error?: string, cachedAt?: int}
     */
    public function audit(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            $this->cacheObject->delete(self::CACHE_KEY);
        }

        return $this->cacheObject->get(self::CACHE_KEY, function (ItemInterface $cacheItem): array {
            $cacheItem->expiresAfter(self::CACHE_TTL_SECONDS);

            return $this->runAudit();
        });
    }

    /**
     * @return array{packages: int, vulnerable: int, advisories: list<array<string, mixed>>, error?: string, cachedAt?: int}
     */
    private function runAudit(): array
    {
        if (!class_exists(InstalledVersions::class)) {
            return [
                'packages' => 0,
                'vulnerable' => 0,
                'advisories' => [],
                'error' => 'Composer runtime metadata is not available',
                'cachedAt' => time(),
            ];
        }

        $packages = $this->collectInstalledPackages();

        if ($packages === []) {
            return [
                'packages' => 0,
                'vulnerable' => 0,
                'advisories' => [],
                'cachedAt' => time(),
            ];
        }

        $advisories = [];

        try {
            foreach (array_chunk(array_keys($packages), self::CHUNK_SIZE) as $chunk) {
                $response = $this->httpClient->request('GET', self::PACKAGIST_AUDIT_URL, [
                    'query' => ['packages' => $chunk],
                    'timeout' => 15,
                ]);

                $data = $response->toArray(false);
                if (!isset($data['advisories']) || !\is_array($data['advisories'])) {
                    continue;
                }

                foreach ($data['advisories'] as $packageName => $packageAdvisories) {
                    if (!\is_array($packageAdvisories) || $packageAdvisories === []) {
                        continue;
                    }

                    $installed = $packages[$packageName] ?? null;
                    $prettyVersion = $installed['pretty'] ?? null;
                    $normalizedVersion = $installed['normalized'] ?? null;

                    foreach ($packageAdvisories as $advisory) {
                        $advisory = \is_array($advisory) ? $advisory : [];

                        if (!$this->affectsInstalledVersion($normalizedVersion, $advisory)) {
                            continue;
                        }

                        $advisories[] = $this->normalizeAdvisory(
                            (string) $packageName,
                            $prettyVersion,
                            $advisory,
                        );
                    }
                }
            }
        } catch (ExceptionInterface $exception) {
            return [
                'packages' => \count($packages),
                'vulnerable' => 0,
                'advisories' => [],
                'error' => \sprintf('Could not contact Packagist: %s', $exception->getMessage()),
                'cachedAt' => time(),
            ];
        }

        usort($advisories, static function (array $a, array $b): int {
            $severityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'moderate' => 2, 'low' => 3, '' => 4];
            $aSev = $severityOrder[strtolower((string) $a['severity'])] ?? 4;
            $bSev = $severityOrder[strtolower((string) $b['severity'])] ?? 4;

            $cmp = $aSev <=> $bSev;
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $a['packageName'], (string) $b['packageName']);
        });

        $vulnerablePackages = [];
        foreach ($advisories as $advisory) {
            $vulnerablePackages[$advisory['packageName']] = true;
        }

        return [
            'packages' => \count($packages),
            'vulnerable' => \count($vulnerablePackages),
            'advisories' => $advisories,
            'cachedAt' => time(),
        ];
    }

    /**
     * @return array<string, array{pretty: string, normalized: string|null}>
     */
    private function collectInstalledPackages(): array
    {
        $packages = [];

        foreach (InstalledVersions::getInstalledPackages() as $package) {
            if ($package === '') {
                continue;
            }

            try {
                $prettyVersion = InstalledVersions::getPrettyVersion($package);
                $normalizedVersion = InstalledVersions::getVersion($package);
            } catch (\OutOfBoundsException) {
                continue;
            }

            if ($prettyVersion === null || $prettyVersion === '') {
                continue;
            }

            $packages[$package] = [
                'pretty' => $prettyVersion,
                'normalized' => $normalizedVersion,
            ];
        }

        return $packages;
    }

    /**
     * @param array<string, mixed> $advisory
     */
    private function affectsInstalledVersion(?string $installedVersion, array $advisory): bool
    {
        if ($installedVersion === null || $installedVersion === '') {
            return true;
        }

        $affectedVersions = isset($advisory['affectedVersions']) ? (string) $advisory['affectedVersions'] : '';
        if ($affectedVersions === '') {
            return true;
        }

        try {
            return Semver::satisfies($installedVersion, $affectedVersions);
        } catch (\UnexpectedValueException) {
            return true;
        }
    }

    /**
     * @param array<string, mixed> $advisory
     *
     * @return array<string, mixed>
     */
    private function normalizeAdvisory(string $packageName, ?string $installedVersion, array $advisory): array
    {
        return [
            'packageName' => $packageName,
            'installedVersion' => $installedVersion,
            'advisoryId' => isset($advisory['advisoryId']) ? (string) $advisory['advisoryId'] : null,
            'cve' => isset($advisory['cve']) ? (string) $advisory['cve'] : null,
            'title' => isset($advisory['title']) ? (string) $advisory['title'] : '',
            'link' => isset($advisory['link']) ? (string) $advisory['link'] : null,
            'affectedVersions' => isset($advisory['affectedVersions']) ? (string) $advisory['affectedVersions'] : '',
            'reportedAt' => isset($advisory['reportedAt']) ? (string) $advisory['reportedAt'] : null,
            'severity' => isset($advisory['severity']) ? (string) $advisory['severity'] : '',
            'sources' => isset($advisory['sources']) && \is_array($advisory['sources']) ? $advisory['sources'] : [],
        ];
    }
}
