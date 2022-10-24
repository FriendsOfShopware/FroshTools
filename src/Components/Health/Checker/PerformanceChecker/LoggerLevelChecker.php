<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Monolog\Handler\AbstractHandler;
use Monolog\Logger;

class LoggerLevelChecker implements CheckerInterface
{
    public const LOGGER_LEVEL_NAME = 'Business logger';

    private int $businessEventHandlerLevel;

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
        if ($this->businessEventHandlerLevel >= Logger::WARNING) {
            return SettingsResult::ok('business_logger', self::LOGGER_LEVEL_NAME, 'BusinessEventHandler does not log infos',
                Logger::getLevelName($this->businessEventHandlerLevel),
                'min WARNING',
                $this->url
            );
        }

        return SettingsResult::warning('business_logger', self::LOGGER_LEVEL_NAME, 'BusinessEventHandler is logging infos',
            Logger::getLevelName($this->businessEventHandlerLevel),
            'min WARNING',
            $this->url
        );
    }
}
