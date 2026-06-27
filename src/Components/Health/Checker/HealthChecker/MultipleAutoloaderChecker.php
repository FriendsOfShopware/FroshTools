<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Composer\InstalledVersions;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

/**
 * Warns when more than one Composer autoloader is registered in the process.
 *
 * Plugins that ship their own `vendor/` directory register an additional autoloader and
 * an additional InstalledVersions dataset. That causes duplicate copies of dependencies to
 * be loaded, breaks Composer's version tracking (the aggregate InstalledVersions methods
 * merge or shadow each other), and can lead to subtle "class already declared" or
 * wrong-version bugs that are hard to diagnose.
 */
class MultipleAutoloaderChecker implements HealthCheckerInterface, CheckerInterface
{
    private const ID = 'multiple-autoloaders';
    private const SNIPPET = 'Composer autoloaders';

    public function collect(HealthCollection $collection): void
    {
        // The same project autoloader can be registered more than once (e.g. shopware/platform
        // loaded via two vendor paths), which produces duplicate datasets that point at the same
        // directory. Those are harmless, so we dedupe by the resolved root install path and only
        // treat a genuinely different directory as an additional autoloader.
        $datasets = $this->uniqueByInstallPath(InstalledVersions::getAllRawData());

        if (\count($datasets) < 2) {
            $collection->add(
                SettingsResult::ok(self::ID, self::SNIPPET, 'single autoloader'),
            );

            return;
        }

        $roots = [];
        foreach ($datasets as $dataset) {
            $name = $dataset['root']['name'];
            $roots[] = $name !== '' && $name !== '__root__' ? $name : $dataset['root']['install_path'];
        }

        $duplicates = $this->collectDuplicatePackages($datasets);

        $current = \sprintf(
            '%d autoloaders registered: %s',
            \count($datasets),
            implode(', ', $roots),
        );

        if ($duplicates !== []) {
            $current .= \sprintf('; duplicate packages: %s', implode(', ', $duplicates));
        }

        $collection->add(
            SettingsResult::warning(
                self::ID,
                self::SNIPPET,
                $current,
                'remove bundled vendor directories so only the project autoloader is used. Contact plugin authors if you are unsure how to do this.',
            ),
        );
    }

    /**
     * Collapses datasets that resolve to the same root install path — the same autoloader
     * registered multiple times is not a real duplicate.
     *
     * @param list<array{root: array{name: string, install_path: string}, versions: array<string, array{pretty_version?: string}>}> $datasets
     *
     * @return list<array{root: array{name: string, install_path: string}, versions: array<string, array{pretty_version?: string}>}>
     */
    private function uniqueByInstallPath(array $datasets): array
    {
        $unique = [];
        foreach ($datasets as $dataset) {
            $path = $dataset['root']['install_path'];
            $resolved = realpath($path);
            $key = $resolved !== false ? $resolved : $path;

            $unique[$key] ??= $dataset;
        }

        return array_values($unique);
    }

    /**
     * Returns packages that are installed by more than one dataset, formatted as
     * "package (versionA, versionB)". These are the dependencies that get loaded twice and
     * are no longer tracked reliably.
     *
     * @param list<array{root: array{name: string, install_path: string}, versions: array<string, array{pretty_version?: string}>}> $datasets
     *
     * @return list<string>
     */
    private function collectDuplicatePackages(array $datasets): array
    {
        /** @var array<string, array{count: int, versions: array<string, true>}> $seen */
        $seen = [];

        foreach ($datasets as $dataset) {
            $rootName = $dataset['root']['name'];

            foreach ($dataset['versions'] as $package => $info) {
                if ($package === '' || $package === $rootName) {
                    continue;
                }

                $version = $info['pretty_version'] ?? null;
                if ($version === null || $version === '') {
                    continue;
                }

                $seen[$package] ??= ['count' => 0, 'versions' => []];
                ++$seen[$package]['count'];
                $seen[$package]['versions'][$version] = true;
            }
        }

        $duplicates = [];
        foreach ($seen as $package => $data) {
            if ($data['count'] < 2) {
                continue;
            }

            $duplicates[] = \sprintf('%s (%s)', $package, implode(', ', array_keys($data['versions'])));
        }

        sort($duplicates);

        return $duplicates;
    }
}
