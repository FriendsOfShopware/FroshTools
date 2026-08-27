<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The Shopware web installer is downloaded as public/shopware-installer.phar.php.
 * After installation it must be deleted: anyone who can request that URL can
 * start a reinstall and overwrite the shop.
 */
class ShopwareInstallerChecker implements HealthCheckerInterface, CheckerInterface
{
    public const ID = 'shopware-installer';
    public const FILE_NAME = 'shopware-installer.phar.php';

    private const SNIPPET = 'Shopware installer';

    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        if (is_file($this->publicDir . '/' . self::FILE_NAME)) {
            $collection->add(SettingsResult::error(
                self::ID,
                self::SNIPPET,
                'present in public directory',
                'not present',
            ));

            return;
        }

        $collection->add(SettingsResult::ok(
            self::ID,
            self::SNIPPET,
            'not present',
            'not present',
        ));
    }
}
