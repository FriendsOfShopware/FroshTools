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
        private readonly CacheInterface $cacheObject
    ) {}

    public function collect(HealthCollection $collection): void
    {
        $this->refreshPlugins($this->connection);

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
}
