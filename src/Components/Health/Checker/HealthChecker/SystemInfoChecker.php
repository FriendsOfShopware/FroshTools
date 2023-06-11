<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;

class SystemInfoChecker implements CheckerInterface
{
    public function __construct(private readonly string $kernelProjectDir)
    {
    }

    public function collect(HealthCollection $collection): void
    {
        $this->checkPath($collection);
        $this->getDatabaseInfo($collection);
    }

    private function checkPath(HealthCollection $collection): void
    {
        $collection->add(
            SettingsResult::ok(
                'installation-path',
                'Installation Path',
                $this->kernelProjectDir
            )
        );
    }

    private function getDatabaseInfo(HealthCollection $collection)
    {
        $databaseConnectionInfo = (new DatabaseConnectionInformation())->fromEnv();

        $collection->add(
            SettingsResult::ok(
                'database-info',
                'Database',
                \sprintf('%s@%s:%s',
                    $databaseConnectionInfo->getDatabaseName(),
                    $databaseConnectionInfo->getHostname(),
                    $databaseConnectionInfo->getPort()
                )
            )
        );
    }
}
