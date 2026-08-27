<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Checker;

use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The Shopware web installer is downloaded as public/shopware-installer.phar.php.
 * After installation it must be deleted: anyone who can request that URL can
 * start a reinstall and overwrite the shop.
 */
class ShopwareInstallerChecker implements SecurityCheckerInterface
{
    public const ID = 'shopware-installer';
    public const FILE_NAME = 'shopware-installer.phar.php';

    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
    }

    public function collect(SecurityCollection $collection): void
    {
        if (is_file($this->publicDir . '/' . self::FILE_NAME)) {
            $collection->add(SecurityFinding::critical(
                self::ID,
                SecurityFinding::CATEGORY_CONFIGURATION,
                'Shopware installer',
                'present in public directory',
                'Delete public/shopware-installer.phar.php. The installer must not remain publicly accessible after installation',
            ));

            return;
        }

        $collection->add(SecurityFinding::ok(
            self::ID,
            SecurityFinding::CATEGORY_CONFIGURATION,
            'Shopware installer',
            'not present',
        ));
    }
}
