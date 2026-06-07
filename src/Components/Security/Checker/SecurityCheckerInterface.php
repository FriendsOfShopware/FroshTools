<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Checker;

use Frosh\Tools\Components\Security\SecurityCollection;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('frosh_tools.security_checker')]
interface SecurityCheckerInterface
{
    public function collect(SecurityCollection $collection): void;
}
