<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class ProductionChecker implements CheckerInterface
{
    public const APPLICATION_VARIABLE_NAME = 'Application variable';

    private string $environment;

    public function __construct(string $environment)
    {
        $this->environment = $environment;
    }

    public function collect(HealthCollection $collection): void
    {
        if ($this->environment !== 'prod') {
            $collection->add(SettingsResult::error('app.env', self::APPLICATION_VARIABLE_NAME, 'Shop is not in production mode', $this->environment, 'prod'));

            return;
        }

        $collection->add(SettingsResult::ok('app.env', self::APPLICATION_VARIABLE_NAME, 'Shop is in production mode', $this->environment, 'prod'));
    }
}
