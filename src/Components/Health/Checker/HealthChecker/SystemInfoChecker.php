<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SystemInfoChecker implements HealthCheckerInterface, CheckerInterface
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
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
                $this->projectDir,
            ),
        );
    }

    private function getDatabaseInfo(HealthCollection $collection): void
    {
        $dsn = trim((string) EnvironmentHelper::getVariable('DATABASE_URL', getenv('DATABASE_URL')));
        $params = parse_url($dsn);

        $port = $params['port'] ?? 3306;
        $path = $params['path'] ?? '';
        if ($path !== '' && $path[0] === '/') {
            $path = substr($path, 1); // Remove leading slash
        }

        $collection->add(
            SettingsResult::ok(
                'database-info',
                'Database',
                \sprintf(
                    '%s@%s:%d/%s',
                    $params['user'] ?? '',
                    $params['host'] ?? '',
                    $port,
                    $path,
                ),
            ),
        );
    }
}
