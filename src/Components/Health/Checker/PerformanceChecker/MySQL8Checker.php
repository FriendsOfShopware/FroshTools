<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class MySQL8Checker implements CheckerInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function collect(HealthCollection $collection): void
    {
        $version = $this->connection->fetchOne('SELECT VERSION()');
        if (!\is_string($version)) {
            return;
        }

        $extractedVersion = $this->extract($version);

        if (isset($extractedVersion['mariadb'])) {
            $collection->add(
                SettingsResult::error('mysql8', 'MySQL', $version, 'MySQL 8.0', 'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks#mysql-instead-of-mariadb')
            );

            return;
        }

        if (version_compare($extractedVersion['mysql'], '8.0.0', '>=')) {
            return;
        }

        $collection->add(
            SettingsResult::error('mysql8', 'MySQL', $version, 'MySQL 8.0', 'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks#mysql-instead-of-mariadb')
        );
    }

    private function extract(string $versionString): array
    {
        if (mb_stripos($versionString, 'mariadb') === false) {
            if (mb_strpos($versionString, '-')) {
                $versionString = mb_substr($versionString, 0, mb_strpos($versionString, '-'));
            }

            return ['mysql' => $versionString];
        }

        return ['mariadb' => self::getVersionNumber($versionString)];
    }

    private static function getVersionNumber(string $versionString): string
    {
        if (!preg_match(
            '/^(?:5\.5\.5-)?(mariadb-)?(?P<major>\d+)\.(?P<minor>\d+)\.(?P<patch>\d+)/i',
            $versionString,
            $versionParts
        )) {
            throw new \RuntimeException(sprintf('Invalid version string: %s', $versionString));
        }

        return $versionParts['major'] . '.' . $versionParts['minor'] . '.' . $versionParts['patch'];
    }
}
