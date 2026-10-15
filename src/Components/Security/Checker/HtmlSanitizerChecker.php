<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Checker;

use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class HtmlSanitizerChecker implements SecurityCheckerInterface
{
    public const ID = 'html-sanitizer';

    public function __construct(
        #[Autowire(param: 'shopware.html_sanitizer.enabled')]
        private readonly bool $enabled,
    ) {
    }

    public function collect(SecurityCollection $collection): void
    {
        if (!$this->enabled) {
            $collection->add(SecurityFinding::high(
                self::ID,
                SecurityFinding::CATEGORY_CONFIGURATION,
                'HTML sanitizer',
                'disabled',
                'Enable shopware.html_sanitizer.enabled to sanitize HTML content and reduce the risk of cross-site scripting (XSS)',
            ));

            return;
        }

        $collection->add(SecurityFinding::ok(
            self::ID,
            SecurityFinding::CATEGORY_CONFIGURATION,
            'HTML sanitizer',
            'enabled',
        ));
    }
}
