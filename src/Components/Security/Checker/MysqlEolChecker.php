<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Checker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Security\EndOfLifeService;
use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;

class MysqlEolChecker implements SecurityCheckerInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EndOfLifeService $endOfLifeService,
    ) {}

    public function collect(SecurityCollection $collection): void
    {
        try {
            $version = $this->connection->fetchOne('SELECT VERSION()');
        } catch (\Throwable) {
            $version = false;
        }

        if (!\is_string($version) || $version === '') {
            $collection->add(SecurityFinding::unknown(
                'database-eol',
                SecurityFinding::CATEGORY_RUNTIME,
                'Database Version',
                'unknown',
                'Could not determine the database version',
            ));

            return;
        }

        [$product, $label, $number] = $this->extract($version);

        if ($number === null) {
            $collection->add(SecurityFinding::unknown(
                'database-eol',
                SecurityFinding::CATEGORY_RUNTIME,
                $label . ' Version',
                $version,
                'Could not parse the database version',
            ));

            return;
        }

        $cycle = $this->endOfLifeService->getCycle($product, $number);

        $collection->add(EolFindingFactory::fromCycle(
            'database-eol',
            $label . ' Version',
            $number,
            $cycle,
            'https://endoflife.date/' . $product,
        ));
    }

    /**
     * @return array{0: string, 1: string, 2: string|null} endoflife.date product, display label, parsed version
     */
    private function extract(string $versionString): array
    {
        if (mb_stripos($versionString, 'mariadb') !== false) {
            return ['mariadb', 'MariaDB', $this->parseMariadbVersion($versionString)];
        }

        $pos = mb_strpos($versionString, '-');
        if ($pos !== false) {
            $versionString = mb_substr($versionString, 0, $pos);
        }

        return ['mysql', 'MySQL', $this->parseVersion($versionString)];
    }

    private function parseMariadbVersion(string $versionString): ?string
    {
        if (preg_match(
            '/^(?:5\.5\.5-)?(?:mariadb-)?(?P<major>\d+)\.(?P<minor>\d+)\.(?P<patch>\d+)/i',
            $versionString,
            $parts,
        ) === 1) {
            return $parts['major'] . '.' . $parts['minor'] . '.' . $parts['patch'];
        }

        return null;
    }

    private function parseVersion(string $versionString): ?string
    {
        if (preg_match('/^(\d+)\.(\d+)(?:\.(\d+))?/', $versionString, $parts) === 1) {
            return $parts[1] . '.' . $parts[2] . (isset($parts[3]) ? '.' . $parts[3] : '');
        }

        return null;
    }
}
