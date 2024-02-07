<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Cache\ItemInterface;

class SwagSecurityChecker implements HealthCheckerInterface, CheckerInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly KernelInterface $kernel,
        #[Autowire('%kernel.shopware_version%')]
        private readonly string $shopwareVersion,
        #[Autowire(service: 'cache.object')]
        private readonly CacheInterface $cacheObject
    ) {}

    public function collect(HealthCollection $collection): void
    {
        $this->refreshPlugins($this->connection);

        $this->determineSecurityIssue($collection);
        $this->determineEolSupport($collection);
    }

    private function refreshPlugins(Connection $connection): void
    {
        $result = $connection->executeQuery(
            'SELECT COUNT(*) FROM plugin WHERE updated_at < DATE_SUB(NOW(), INTERVAL 1 DAY)'
        )->fetchOne();

        if (empty($result)) {
            return;
        }

        $pluginRefresh = new ArrayInput([
            'command' => 'plugin:refresh',
        ]);
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $application->run($pluginRefresh, new NullOutput());
    }

    private function hasSecurityAdvisories(): bool
    {
        $cacheKey = \sprintf('security-advisories-%s', $this->shopwareVersion);

        return $this->cacheObject->get($cacheKey, function (ItemInterface $cacheItem) {
            $securityJson = file_get_contents('https://raw.githubusercontent.com/FriendsOfShopware/shopware-static-data/main/data/security.json');
            if ($securityJson === false) {
                throw new \RuntimeException('Could not fetch security.json');
            }

            $data = \json_decode(trim($securityJson), true, 512, JSON_THROW_ON_ERROR);

            if (!\is_array($data)) {
                throw new \RuntimeException('Could not read security.json');
            }

            $cacheItem->expiresAfter(3600 * 24);

            return isset($data['versionToAdvisories']['v' . $this->shopwareVersion]);
        });
    }

    private function swagSecurityInstalled(): bool
    {
        $result = $this->connection->executeQuery(
            'SELECT COUNT(*) FROM plugin WHERE active = 1 AND installed_at IS NOT NULL AND upgrade_version IS NULL AND name = :pluginName',
            ['pluginName' => 'SwagPlatformSecurity'],
        )->fetchOne();

        return !empty($result);
    }

    private function determineSecurityIssue(HealthCollection $collection): void
    {
        try {
            if (!$this->hasSecurityAdvisories()) {
                return;
            }
        } catch (\Throwable) {
            $collection->add(
                SettingsResult::error(
                    'security-update',
                    'Cannot check security.json from shopware-static-data',
                    'not accessible',
                    'accessible',
                    'https://raw.githubusercontent.com/FriendsOfShopware/shopware-static-data/main/data/security.json'
                )
            );
        }

        if ($this->swagSecurityInstalled()) {
            return;
        }

        $collection->add(
            SettingsResult::error(
                'security-update',
                'Security update',
                'Shopware outdated',
                'Update Shopware to the latest version or install recent version of the plugin SwagPlatformSecurity',
                'https://store.shopware.com/en/swag136939272659f/shopware-6-security-plugin.html'
            )
        );
    }

    private function determineEolSupport(HealthCollection $collection): void
    {

        $id = 'security-eol-shopware';
        $snippet = 'Security updates';

        try {
            $releaseSupport = $this->getReleasesSupport();
        } catch (\Throwable) {
            $collection->add(
                SettingsResult::error(
                    $id,
                    $snippet,
                    'releases.json not accessible',
                    'accessible',
                    'https://raw.githubusercontent.com/shopware/shopware/trunk/releases.json'
                )
            );

            return;
        }

        $recommended = 'Please update to a more recent version and minimum LTS version ' . ($releaseSupport['extended_eol_version'] ?? 'unknown') . '.';

        if (empty($releaseSupport['security_eol'])) {
            $collection->add(
                SettingsResult::error(
                    $id,
                    $snippet,
                    'unknown, possibly ended security support',
                    $recommended
                )
            );

            return;
        }

        $securityEol = new \DateTime($releaseSupport['security_eol']);

        if ($securityEol < (new \DateTime())) {
            $collection->add(
                SettingsResult::error(
                    $id,
                    $snippet,
                    'ended security support on ' . $releaseSupport['security_eol'],
                    $recommended
                )
            );

            return;
        }

        if ($securityEol < (new \DateTime())->modify('+6 month')) {
            $collection->add(
                SettingsResult::warning(
                    $id,
                    $snippet,
                    'less than six months (' . $releaseSupport['security_eol'] . ')',
                    $recommended
                )
            );

            return;
        }

        if ($securityEol < (new \DateTime())->modify('+1 year')) {
            $collection->add(
                SettingsResult::info(
                    $id,
                    $snippet,
                    'less than one year (' . $releaseSupport['security_eol'] . ')',
                    $recommended
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok(
                $id,
                $snippet,
                'until ' . $releaseSupport['security_eol']
            )
        );
    }

    /**
     * @return array{version?: string, release_date?: string, extended_eol?: string|false, security_eol?: string, extended_eol_version?: string}
     */
    private function getReleasesSupport(): array
    {
        $cacheKey = \sprintf('shopware-releases-support-%s', $this->shopwareVersion);

        return $this->cacheObject->get($cacheKey, function (ItemInterface $cacheItem) {
            $releasesJson = file_get_contents('https://raw.githubusercontent.com/shopware/shopware/trunk/releases.json');
            if ($releasesJson === false) {
                throw new \RuntimeException('Could not fetch releases.json');
            }

            $data = \json_decode(trim($releasesJson), true, 512, JSON_THROW_ON_ERROR);

            if (!\is_array($data)) {
                throw new \RuntimeException('Could not read releases.json');
            }

            $cacheItem->expiresAfter(3600 * 24 * 14);

            $result = [];

            foreach ($data as $entry) {
                if (empty($entry['version'])) {
                    continue;
                }

                if (!empty($entry['extended_eol'])
                    && version_compare($entry['version'], ($result['extended_eol_version'] ?? ''), '>')) {
                    $result['extended_eol_version'] = $entry['version'];
                }

                if (version_compare($entry['version'], ($result['version'] ?? ''), '>')
                    && version_compare($entry['version'], $this->shopwareVersion, '<=')) {
                    $result = [...$result, ...$entry];
                }
            }

            return $result;
        });
    }
}
