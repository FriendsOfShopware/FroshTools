<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Sbom;

use Composer\InstalledVersions;
use Composer\Spdx\SpdxLicenses;
use Frosh\Tools\Components\Exception\FroshToolsException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds a CycloneDX 1.7 JSON SBOM from the project composer.lock.
 *
 * Ported from https://github.com/shyim/go-composer/tree/main/sbom
 */
class CycloneDxSbomGenerator
{
    private const BOM_FORMAT = 'CycloneDX';
    private const SPEC_VERSION = '1.7';
    private const TOOL_NAME = 'FroshTools';
    private const TOOL_GROUP = 'frosh';

    private ?SpdxLicenses $spdxLicenses = null;
    private bool $spdxInitialized = false;

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
        #[Autowire(param: 'kernel.shopware_version')]
        private readonly string $shopwareVersion,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(bool $includeDevDependencies = false): array
    {
        $lock = $this->readLock();
        $root = $this->readRootComposer();

        $applicationName = $root['name'] ?? 'application';
        if (!\is_string($applicationName) || $applicationName === '') {
            $applicationName = 'application';
        }

        $applicationVersion = '';
        if (isset($root['version']) && \is_string($root['version']) && $root['version'] !== '') {
            $applicationVersion = $root['version'];
        } elseif ($this->shopwareVersion !== '') {
            $applicationVersion = $this->shopwareVersion;
        }

        $rootRef = 'app:' . $applicationName;
        if ($applicationVersion !== '') {
            $rootRef .= '@' . $applicationVersion;
        }

        $packages = $lock['packages'] ?? [];
        if (!\is_array($packages)) {
            $packages = [];
        }

        if ($includeDevDependencies) {
            $devPackages = $lock['packages-dev'] ?? [];
            if (\is_array($devPackages)) {
                $packages = array_merge($packages, $devPackages);
            }
        }

        $components = [];
        /** @var array<string, string> $refByName package name => bom-ref */
        $refByName = [];

        foreach ($packages as $package) {
            if (!\is_array($package)) {
                continue;
            }

            $component = $this->componentFromPackage($package);
            $name = isset($package['name']) && \is_string($package['name']) ? $package['name'] : '';
            if ($name !== '') {
                $refByName[$name] = $component['bom-ref'];
            }
            $components[] = $component;
        }

        $providerByName = $this->buildProviderIndex($packages, $refByName);
        $rootRequirements = $this->rootRequirements($root, $includeDevDependencies);

        $bom = [
            'bomFormat' => self::BOM_FORMAT,
            'specVersion' => self::SPEC_VERSION,
            'serialNumber' => $this->newSerialNumber(),
            'version' => 1,
            'metadata' => [
                'timestamp' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
                'tools' => [
                    'components' => [
                        [
                            'type' => 'application',
                            'group' => self::TOOL_GROUP,
                            'name' => self::TOOL_NAME,
                            'version' => $this->toolVersion(),
                        ],
                    ],
                ],
                'component' => array_filter([
                    'type' => 'application',
                    'bom-ref' => $rootRef,
                    'name' => $applicationName,
                    'version' => $applicationVersion !== '' ? $applicationVersion : null,
                ], static fn ($value) => $value !== null),
            ],
            'components' => $components,
            'dependencies' => $this->buildDependencies($packages, $refByName, $providerByName, $rootRef, $rootRequirements),
        ];

        return $bom;
    }

    public function generateJson(bool $includeDevDependencies = false): string
    {
        return json_encode(
            $this->generate($includeDevDependencies),
            \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES,
        ) . "\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function readLock(): array
    {
        $path = $this->projectDir . '/composer.lock';
        if (!is_file($path) || !is_readable($path)) {
            throw FroshToolsException::composerLockMissing();
        }

        $contents = file_get_contents($path);
        if ($contents === false || $contents === '') {
            throw FroshToolsException::composerLockMissing();
        }

        try {
            $data = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw FroshToolsException::composerLockInvalid($exception->getMessage());
        }

        if (!\is_array($data)) {
            throw FroshToolsException::composerLockInvalid('decoded value is not an object');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function readRootComposer(): array
    {
        $path = $this->projectDir . '/composer.json';
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false || $contents === '') {
            return [];
        }

        try {
            $data = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($data) ? $data : [];
    }

    /**
     * Collects direct root package names from composer.json require / require-dev.
     *
     * @param array<string, mixed> $root
     *
     * @return list<string>
     */
    private function rootRequirements(array $root, bool $includeDevDependencies): array
    {
        $names = [];

        foreach ($this->requirementNames($root['require'] ?? null) as $name) {
            $names[] = $name;
        }

        if ($includeDevDependencies) {
            foreach ($this->requirementNames($root['require-dev'] ?? null) as $name) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    private function requirementNames(mixed $require): array
    {
        if (!\is_array($require)) {
            return [];
        }

        $names = [];
        foreach (array_keys($require) as $name) {
            if (!\is_string($name) || $name === '' || $this->isPlatformPackage($name)) {
                continue;
            }
            $names[] = $name;
        }

        return $names;
    }

    /**
     * Maps package names (including provide/replace aliases) to the bom-ref of the
     * installed package that satisfies them.
     *
     * @param array<mixed> $packages
     * @param array<string, string> $refByName
     *
     * @return array<string, string>
     */
    private function buildProviderIndex(array $packages, array $refByName): array
    {
        $providerByName = $refByName;

        foreach ($packages as $package) {
            if (!\is_array($package)) {
                continue;
            }

            $name = isset($package['name']) && \is_string($package['name']) ? $package['name'] : '';
            if ($name === '' || !isset($refByName[$name])) {
                continue;
            }

            $ref = $refByName[$name];

            foreach (['provide', 'replace'] as $field) {
                $aliases = $package[$field] ?? null;
                if (!\is_array($aliases)) {
                    continue;
                }

                foreach (array_keys($aliases) as $alias) {
                    if (!\is_string($alias) || $alias === '' || $this->isPlatformPackage($alias)) {
                        continue;
                    }

                    // Prefer a real installed package over an alias when both exist.
                    if (!isset($providerByName[$alias])) {
                        $providerByName[$alias] = $ref;
                    }
                }
            }
        }

        return $providerByName;
    }

    /**
     * @param array<string, mixed> $package
     *
     * @return array<string, mixed>
     */
    private function componentFromPackage(array $package): array
    {
        $name = isset($package['name']) && \is_string($package['name']) ? $package['name'] : 'unknown';
        $version = isset($package['version']) && \is_string($package['version']) ? $package['version'] : '';
        $type = isset($package['type']) && \is_string($package['type']) ? $package['type'] : '';
        $description = isset($package['description']) && \is_string($package['description']) ? $package['description'] : '';
        $homepage = isset($package['homepage']) && \is_string($package['homepage']) ? $package['homepage'] : '';

        $purl = $this->buildPurl($name, $version);
        [$group, $pkgName] = $this->splitComposerName($name);

        $component = array_filter([
            'type' => $this->cyclonedxType($type),
            'bom-ref' => $purl,
            'group' => $group !== '' ? $group : null,
            'name' => $pkgName,
            'version' => $version !== '' ? $version : null,
            'description' => $description !== '' ? $description : null,
            'purl' => $purl,
        ], static fn ($value) => $value !== null);

        $licenses = $this->licensesFromPackage($package['license'] ?? null);
        if ($licenses !== []) {
            $component['licenses'] = $licenses;
        }

        $dist = isset($package['dist']) && \is_array($package['dist']) ? $package['dist'] : [];
        $source = isset($package['source']) && \is_array($package['source']) ? $package['source'] : [];

        $shasum = isset($dist['shasum']) && \is_string($dist['shasum']) ? $dist['shasum'] : '';
        if ($shasum !== '') {
            // Composer lock dist.shasum is a SHA-1 hex digest.
            $component['hashes'] = [
                ['alg' => 'SHA-1', 'content' => $shasum],
            ];
        }

        $externalReferences = [];
        if ($homepage !== '') {
            $externalReferences[] = ['type' => 'website', 'url' => $homepage];
        }

        $sourceUrl = isset($source['url']) && \is_string($source['url']) ? $source['url'] : '';
        if ($sourceUrl !== '') {
            $externalReferences[] = ['type' => 'vcs', 'url' => $sourceUrl];
        }

        $distUrl = isset($dist['url']) && \is_string($dist['url']) ? $dist['url'] : '';
        if ($distUrl !== '') {
            $externalReferences[] = ['type' => 'distribution', 'url' => $distUrl];
        }

        if ($externalReferences !== []) {
            $component['externalReferences'] = $externalReferences;
        }

        return $component;
    }

    /**
     * @param array<mixed> $packages
     * @param array<string, string> $refByName real package name => bom-ref
     * @param array<string, string> $providerByName package/alias name => bom-ref
     * @param list<string> $rootRequirements
     *
     * @return list<array{ref: string, dependsOn?: list<string>}>
     */
    private function buildDependencies(
        array $packages,
        array $refByName,
        array $providerByName,
        string $rootRef,
        array $rootRequirements,
    ): array {
        $dependencies = [];

        $rootDeps = $this->resolveRequirementRefs($rootRequirements, $providerByName);
        $rootEntry = ['ref' => $rootRef];
        if ($rootDeps !== []) {
            $rootEntry['dependsOn'] = $rootDeps;
        }
        $dependencies[] = $rootEntry;

        foreach ($packages as $package) {
            if (!\is_array($package)) {
                continue;
            }

            $name = isset($package['name']) && \is_string($package['name']) ? $package['name'] : '';
            if ($name === '' || !isset($refByName[$name])) {
                continue;
            }

            $require = isset($package['require']) && \is_array($package['require']) ? $package['require'] : [];
            $dependsOn = $this->resolveRequirementRefs(
                $this->requirementNames($require),
                $providerByName,
            );

            $entry = ['ref' => $refByName[$name]];
            if ($dependsOn !== []) {
                $entry['dependsOn'] = $dependsOn;
            }
            $dependencies[] = $entry;
        }

        return $dependencies;
    }

    /**
     * @param list<string> $requirements
     * @param array<string, string> $providerByName
     *
     * @return list<string>
     */
    private function resolveRequirementRefs(array $requirements, array $providerByName): array
    {
        $refs = [];
        foreach ($requirements as $required) {
            if (!isset($providerByName[$required])) {
                continue;
            }
            $refs[] = $providerByName[$required];
        }

        $refs = array_values(array_unique($refs));
        sort($refs);

        return $refs;
    }

    /**
     * @return list<array{license: array{id?: string, name?: string}}>
     */
    private function licensesFromPackage(mixed $license): array
    {
        $values = [];
        if (\is_string($license) && $license !== '') {
            $values = [$license];
        } elseif (\is_array($license)) {
            foreach ($license as $item) {
                if (\is_string($item) && trim($item) !== '') {
                    $values[] = trim($item);
                }
            }
        }

        if ($values === []) {
            return [];
        }

        $out = [];
        foreach ($values as $value) {
            if ($this->isSpdxLicenseId($value)) {
                $out[] = ['license' => ['id' => $value]];
            } else {
                $out[] = ['license' => ['name' => $value]];
            }
        }

        return $out;
    }

    private function isSpdxLicenseId(string $license): bool
    {
        if (!$this->spdxInitialized) {
            $this->spdxInitialized = true;
            if (class_exists(SpdxLicenses::class)) {
                try {
                    $this->spdxLicenses = new SpdxLicenses();
                } catch (\Throwable) {
                    $this->spdxLicenses = null;
                }
            }
        }

        if ($this->spdxLicenses === null) {
            return false;
        }

        try {
            return (bool) $this->spdxLicenses->validate($license);
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    private function isPlatformPackage(string $name): bool
    {
        if ($name === 'php' || $name === 'hhvm') {
            return true;
        }

        foreach (['php-', 'ext-', 'lib-', 'composer-'] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Builds a Package URL (purl) with percent-encoded name segments and version
     * per https://github.com/package-url/purl-spec
     */
    private function buildPurl(string $name, string $version): string
    {
        $segments = explode('/', $name);
        $encoded = [];
        foreach ($segments as $segment) {
            $encoded[] = rawurlencode($segment);
        }

        $purl = 'pkg:composer/' . implode('/', $encoded);
        if ($version !== '') {
            $purl .= '@' . rawurlencode($version);
        }

        return $purl;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitComposerName(string $name): array
    {
        $pos = strpos($name, '/');
        if ($pos !== false && $pos > 0) {
            return [substr($name, 0, $pos), substr($name, $pos + 1)];
        }

        return ['', $name];
    }

    private function cyclonedxType(string $composerType): string
    {
        return match ($composerType) {
            'project' => 'application',
            default => 'library',
        };
    }

    private function toolVersion(): string
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('frosh/tools')) {
            $version = InstalledVersions::getPrettyVersion('frosh/tools');
            if (\is_string($version) && $version !== '') {
                return $version;
            }
        }

        return 'unknown';
    }

    private function newSerialNumber(): string
    {
        $bytes = random_bytes(16);
        // UUID v4 per RFC 4122.
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3F) | 0x80);

        $hex = bin2hex($bytes);

        return \sprintf(
            'urn:uuid:%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
