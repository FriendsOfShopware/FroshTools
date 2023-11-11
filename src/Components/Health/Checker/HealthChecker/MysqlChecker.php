<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class MysqlChecker implements HealthCheckerInterface, CheckerInterface
{
    public function __construct(private readonly Connection $connection) {}

    public function collect(HealthCollection $collection): void
    {
        $version = $this->connection->fetchOne('SELECT VERSION()');
        if (!\is_string($version)) {
            $collection->add(SettingsResult::error('mysql', 'MySQL Version', 'unknown'));

            return;
        }

        $extractedVersion = $this->extract($version);

        if (isset($extractedVersion['mariadb'])) {
            $this->checkMariadbVersion($collection, $extractedVersion['mariadb']);

            return;
        }

        if (isset($extractedVersion['mysql'])) {
            $this->checkMysqlVersion($collection, $extractedVersion['mysql']);

            return;
        }

        $collection->add(SettingsResult::error('mysql', 'MySQL Version cannot be checked'));
    }

    private function checkMariadbVersion(HealthCollection $collection, string $version): void
    {
        $minVersion = '10.3';

        if (version_compare($version, $minVersion, '>=')) {
            $collection->add(SettingsResult::ok(
                'mysql',
                'MariaDB Version',
                $version,
                'min ' . $minVersion
            ));
        }
    }

    private function checkMysqlVersion(HealthCollection $collection, string $version): void
    {
        $minVersion = '8.0';
        $brokenVersions = [
            '8.0.20',
            '8.0.21',
        ];
        $snippet = 'MySQL Version';

        $recommended = 'min ' . $minVersion . ', but not ' . \implode(' or ', $brokenVersions);

        if (\in_array($version, $brokenVersions, true)) {
            $collection->add(SettingsResult::error(
                'mysql',
                $snippet,
                $version,
                $recommended
            ));

            return;
        }

        if (version_compare($version, $minVersion, '>=')) {
            $collection->add(SettingsResult::ok(
                'mysql',
                $snippet,
                $version,
                $recommended
            ));

            return;
        }

        $collection->add(SettingsResult::error(
            'mysql',
            $snippet,
            $version,
            'min ' . $minVersion
        ));
    }

    /**
     * @return array{mysql?: string, mariadb?: string}
     */
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
