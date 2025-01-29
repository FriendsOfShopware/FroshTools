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
                $this->projectDir,
            ),
        );
    }

    private function getDatabaseInfo(HealthCollection $collection): void
    {
        $result = new SettingsResult();
        $result->assign([
            'id' => 'database-info',
            'snippet' => 'Database',
            'current' => 'unknown',
        ]);

        try {
            $dsn = trim((string) EnvironmentHelper::getVariable('DATABASE_URL', getenv('DATABASE_URL')));
            if ($dsn === '') {
                return;
            }

            $params = parse_url($dsn);
            if ($params === false) {
                return;
            }

            foreach ($params as $param => $value) {
                if (!\is_string($value)) {
                    continue;
                }

                $params[$param] = rawurldecode($value);
            }

            $path = (string) ($params['path'] ?? '/');
            $dbName = trim(substr($path, 1));

            $result->current =\sprintf(
                '%s@%s:%d/%s',
                $params['user'] ?? null,
                $params['host'] ?? null,
                (int) ($params['port'] ?? '3306'),
                $dbName,
            );
        } finally {
            $collection->add($result);
        }
    }
}
