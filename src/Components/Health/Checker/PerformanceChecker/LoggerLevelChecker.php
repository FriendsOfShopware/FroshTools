<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Monolog\Handler\AbstractHandler;
use Monolog\Level;
use Monolog\Logger;

class LoggerLevelChecker implements CheckerInterface
{
    private readonly Level $businessEventHandlerLevel;

    private string $url = 'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks#logging';

    public function __construct(AbstractHandler $businessEventHandlerLevel)
    {
        $this->businessEventHandlerLevel = $businessEventHandlerLevel->getLevel();
    }

    public function collect(HealthCollection $collection): void
    {
        $collection->add(
            $this->checkBusinessEventHandlerLevel()
        );
    }

    private function checkBusinessEventHandlerLevel(): SettingsResult
    {
        if ($this->businessEventHandlerLevel->isHigherThan(Level::Warning)) {
            return SettingsResult::ok(
                'business_logger',
                'BusinessEventHandler does not log infos',
                Logger::toMonologLevel($this->businessEventHandlerLevel)->getName(),
                'min WARNING',
                $this->url
            );
        }

        return SettingsResult::warning(
            'business_logger',
            'BusinessEventHandler is logging infos',
            Logger::toMonologLevel($this->businessEventHandlerLevel)->getName(),
            'min WARNING',
            $this->url
        );
    }
}
