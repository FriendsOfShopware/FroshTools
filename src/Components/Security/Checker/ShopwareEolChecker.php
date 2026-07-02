<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Checker;

use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;
use Frosh\Tools\Components\Security\ShopwareReleaseService;

class ShopwareEolChecker implements SecurityCheckerInterface
{
    private const ID = 'shopware-security-eol';
    private const TITLE = 'Shopware security support';

    public function __construct(
        private readonly ShopwareReleaseService $releaseService,
    ) {}

    public function collect(SecurityCollection $collection): void
    {
        try {
            $releaseSupport = $this->releaseService->getReleasesSupport();
        } catch (\Throwable) {
            $collection->add(SecurityFinding::unknown(
                self::ID,
                SecurityFinding::CATEGORY_UPDATES,
                self::TITLE,
                'releases.json not accessible',
                'Ensure the shop can reach raw.githubusercontent.com',
                'https://raw.githubusercontent.com/shopware/shopware/trunk/releases.json',
            ));

            return;
        }

        $version = $this->releaseService->getShopwareVersion();
        $recommended = 'Update to a more recent version, minimum LTS ' . ($releaseSupport['extended_eol_version'] ?? 'unknown');

        if (!isset($releaseSupport['security_eol']) || $releaseSupport['security_eol'] === '') {
            $collection->add(SecurityFinding::critical(
                self::ID,
                SecurityFinding::CATEGORY_UPDATES,
                self::TITLE,
                $version . ' (unknown, possibly ended security support)',
                $recommended,
            ));

            return;
        }

        $securityEol = new \DateTimeImmutable($releaseSupport['security_eol']);
        $now = new \DateTimeImmutable();

        if ($securityEol < $now) {
            $collection->add(SecurityFinding::critical(
                self::ID,
                SecurityFinding::CATEGORY_UPDATES,
                self::TITLE,
                $version . ' (security support ended on ' . $releaseSupport['security_eol'] . ')',
                $recommended,
            ));

            return;
        }

        if ($securityEol < $now->modify('+6 months')) {
            $collection->add(SecurityFinding::high(
                self::ID,
                SecurityFinding::CATEGORY_UPDATES,
                self::TITLE,
                $version . ' (security support ends ' . $releaseSupport['security_eol'] . ', less than 6 months)',
                $recommended,
            ));

            return;
        }

        if ($securityEol < $now->modify('+1 year')) {
            $collection->add(SecurityFinding::medium(
                self::ID,
                SecurityFinding::CATEGORY_UPDATES,
                self::TITLE,
                $version . ' (security support ends ' . $releaseSupport['security_eol'] . ', less than 1 year)',
                $recommended,
            ));

            return;
        }

        $collection->add(SecurityFinding::ok(
            self::ID,
            SecurityFinding::CATEGORY_UPDATES,
            self::TITLE,
            $version . ' (supported until ' . $releaseSupport['security_eol'] . ')',
        ));
    }
}
