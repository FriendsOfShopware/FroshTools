<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Checker;

use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;

class PdoParserChecker implements SecurityCheckerInterface
{
    public const ID = 'pdo-driver-specific-parsers';
    public const REFERENCE = 'https://wiki.php.net/rfc/pdo_driver_specific_parsers';

    public function __construct(
        private readonly string $phpVersion = PHP_VERSION,
    ) {
    }

    public function collect(SecurityCollection $collection): void
    {
        if (version_compare($this->phpVersion, '8.4.0', '<')) {
            $collection->add(SecurityFinding::medium(
                self::ID,
                SecurityFinding::CATEGORY_RUNTIME,
                'PDO driver-specific parsers',
                $this->phpVersion,
                'Upgrade to PHP 8.4 or later. Driver-specific PDO parsers reduce the risk of SQL injection caused by mismatches between PDO and database SQL parsing.',
                self::REFERENCE,
            ));

            return;
        }

        $collection->add(SecurityFinding::ok(
            self::ID,
            SecurityFinding::CATEGORY_RUNTIME,
            'PDO driver-specific parsers',
            $this->phpVersion,
        ));
    }
}
