<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\ComposerAudit;

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
    private const SHOPWARE_CORE_PACKAGES = [
        'shopware/core',
        'shopware/administration',
        'shopware/storefront',
        'shopware/elasticsearch',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cacheObject,
        private readonly Connection $connection,
        #[Autowire(param: 'kernel.shopware_version')]
        private readonly string $shopwareVersion,
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
        $suppressShopwareAdvisories = $this->hasUpToDateSecurityPlugin();

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

                    if ($suppressShopwareAdvisories && \in_array((string) $packageName, self::SHOPWARE_CORE_PACKAGES, true)) {
                        continue;
                    }

                    // The same package may be installed at several versions across vendor dirs;
                    // audit each version on its own so we only flag the ones actually affected.
                    foreach ($packages[$packageName] ?? [] as $installed) {
                        foreach ($packageAdvisories as $advisory) {
                            $advisory = \is_array($advisory) ? $advisory : [];

                            if (!$this->affectsInstalledVersion($installed['normalized'], $advisory)) {
                                continue;
                            }

                            $advisories[] = $this->normalizeAdvisory(
                                (string) $packageName,
                                $installed['pretty'],
                                $installed['sources'],
                                $advisory,
                            );
                        }
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
     * Collects every installed package across all Composer datasets.
     *
     * Plugins can ship their own autoloader and register an additional dataset with
     * InstalledVersions. The aggregate methods (getInstalledPackages/getRootPackage/...)
     * merge across all of them, which mixes the plugin's dependency tree into the root and
     * collapses the same package to a single version. getAllRawData() lets us read each
     * dataset on its own, so we can keep distinct versions apart and remember which roots
     * shipped each one — a plugin bundling an outdated, vulnerable copy stays visible even
     * when the project root is already patched.
     *
     * The result is keyed by package name, then by pretty version, so each distinct
     * (package, version) pair is audited on its own with the list of sources that ship it.
     *
     * @return array<string, array<string, array{pretty: string, normalized: string|null, sources: list<string>}>>
     */
    private function collectInstalledPackages(): array
    {
        $packages = [];

        foreach (InstalledVersions::getAllRawData() as $index => $dataset) {
            $rootPackageName = $dataset['root']['name'];

            // The first dataset is the project itself; every other dataset is registered by a
            // plugin shipping its own vendor dir. Label the project explicitly, plugins by name.
            $source = $index === 0 ? 'project' : $rootPackageName;

            foreach ($dataset['versions'] as $package => $info) {
                if ($package === '' || $package === 'shopware/production') {
                    continue;
                }

                // Replaced/provided packages have no version of their own.
                $prettyVersion = $info['pretty_version'] ?? null;
                if ($prettyVersion === null || $prettyVersion === '') {
                    continue;
                }

                if (!isset($packages[$package][$prettyVersion])) {
                    $packages[$package][$prettyVersion] = [
                        'pretty' => $prettyVersion,
                        'normalized' => $info['version'] ?? null,
                        'sources' => [],
                    ];
                }

                if (!\in_array($source, $packages[$package][$prettyVersion]['sources'], true)) {
                    $packages[$package][$prettyVersion]['sources'][] = $source;
                }
            }
        }

        return $packages;
    }

    private function hasUpToDateSecurityPlugin(): bool
    {
        try {
            $installedVersion = $this->connection->executeQuery(
                'SELECT version FROM plugin WHERE active = 1 AND installed_at IS NOT NULL AND name = :pluginName',
                ['pluginName' => 'SwagPlatformSecurity'],
            )->fetchOne();
        } catch (\Throwable) {
            return false;
        }

        if (!\is_string($installedVersion) || $installedVersion === '') {
            return false;
        }

        try {
            $recentVersion = $this->fetchLatestSecurityPluginVersion();
        } catch (\Throwable) {
            return false;
        }

        if ($recentVersion === null) {
            return false;
        }

        return version_compare($installedVersion, $recentVersion, '>=');
    }

    private function fetchLatestSecurityPluginVersion(): ?string
    {
        $cacheKey = \sprintf('recent-security-plugin-version-%s', $this->shopwareVersion);

        return $this->cacheObject->get($cacheKey, function (ItemInterface $cacheItem): ?string {
            $cacheItem->expiresAfter(3600 * 24);

            $result = $this->httpClient->request(
                'GET',
                \sprintf(
                    'https://api.shopware.com/pluginStore/pluginsByName?shopwareVersion=%s&technicalNames[]=SwagPlatformSecurity',
                    $this->shopwareVersion,
                ),
            )->getContent();

            $data = \json_decode(trim($result), true, 512, \JSON_THROW_ON_ERROR);

            if (!\is_array($data)) {
                return null;
            }

            $version = $data[0]['version'] ?? null;

            return \is_string($version) && $version !== '' ? $version : null;
        });
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
     * @param list<string> $sources
     * @param array<string, mixed> $advisory
     *
     * @return array<string, mixed>
     */
    private function normalizeAdvisory(string $packageName, ?string $installedVersion, array $sources, array $advisory): array
    {
        return [
            'packageName' => $packageName,
            'installedVersion' => $installedVersion,
            'installedSources' => $sources,
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
