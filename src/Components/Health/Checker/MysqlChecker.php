<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\HealthResult;

class MysqlChecker implements CheckerInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function collect(HealthCollection $collection): void
    {
        $version = $this->connection->fetchOne('SELECT VERSION()');
        $extractedVersion = $this->extract($version);

        if (isset($extractedVersion['mariadb'])) {
            $this->checkMariadbVersion($collection, $extractedVersion['mariadb']);

            return;
        }

        if (isset($extractedVersion['mysql'])) {
            $this->checkMysqlVersion($collection, $extractedVersion['mysql']);

            return;
        }

        $collection->add(HealthResult::error('frosh-tools.checker.mysqlError'));
    }

    private function checkMariadbVersion($collection, $version): void
    {
        $minVersion = '10.3';

        if (version_compare($version, $minVersion, '>=')) {
            $collection->add(HealthResult::ok('frosh-tools.checker.mariaDbVersion', ['version' => $version]));
        }
    }

    private function checkMysqlVersion($collection, $version): void
    {
        $minVersion = '5.7.21';
        $brokenVersions = [
            '8.0.20',
            '8.0.21',
        ];

        if (in_array($version, $brokenVersions, true)) {
            $collection->add(HealthResult::error('frosh-tools.checker.mysqlDbVersionError', ['version' => $version]));

            return;
        }

        if (version_compare($version, $minVersion, '>=')) {
            $collection->add(HealthResult::ok('frosh-tools.checker.mysqlDbVersion', ['version' => $version]));

            return;
        }

        $collection->add(HealthResult::error('frosh-tools.checker.mysqlDbOutdated', ['version' => $version]));
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
