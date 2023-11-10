<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SystemInfoChecker implements HealthCheckerInterface, CheckerInterface
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir
    ) {}

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
                $this->projectDir
            )
        );
    }

    private function getDatabaseInfo(HealthCollection $collection): void
    {
        $databaseConnectionInfo = (new DatabaseConnectionInformation())->fromEnv();

        $collection->add(
            SettingsResult::ok(
                'database-info',
                'Database',
                \sprintf(
                    '%s@%s:%d/%s',
                    $databaseConnectionInfo->getUsername(),
                    $databaseConnectionInfo->getHostname(),
                    $databaseConnectionInfo->getPort(),
                    $databaseConnectionInfo->getDatabaseName()
                )
            )
        );
    }
}
