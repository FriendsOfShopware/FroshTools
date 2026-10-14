<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Checker;

use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Surfaces the effective state of the Administration password recovery
 * ("forgot password") flow. The flow is disabled when either
 * frosh_tools.user_recovery.enabled or, on Shopware versions with core
 * support (shopware/shopware#21637), shopware.api.user_recovery.enabled
 * is false.
 */
class UserRecoveryChecker implements SecurityCheckerInterface
{
    public const ID = 'admin-user-recovery';

    public function __construct(
        #[Autowire(param: 'frosh_tools.user_recovery.enabled')]
        private readonly bool $froshToolsEnabled,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    public function collect(SecurityCollection $collection): void
    {
        if (!$this->froshToolsEnabled) {
            $collection->add(SecurityFinding::ok(
                self::ID,
                SecurityFinding::CATEGORY_CONFIGURATION,
                'Administration password recovery',
                'disabled via FroshTools config (frosh_tools.user_recovery.enabled: false)',
            ));

            return;
        }

        if ($this->parameterBag->has('shopware.api.user_recovery.enabled')
            && $this->parameterBag->get('shopware.api.user_recovery.enabled') === false) {
            $collection->add(SecurityFinding::ok(
                self::ID,
                SecurityFinding::CATEGORY_CONFIGURATION,
                'Administration password recovery',
                'disabled via Shopware config (shopware.api.user_recovery.enabled: false)',
            ));

            return;
        }

        $collection->add(SecurityFinding::low(
            self::ID,
            SecurityFinding::CATEGORY_CONFIGURATION,
            'Administration password recovery',
            'enabled',
            'Set frosh_tools.user_recovery.enabled to false when Administration passwords are managed externally, e.g. via SSO (on supported Shopware versions shopware.api.user_recovery.enabled works as well)',
        ));
    }
}
