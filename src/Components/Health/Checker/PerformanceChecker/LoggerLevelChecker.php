<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Monolog\Handler\AbstractHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class LoggerLevelChecker implements PerformanceCheckerInterface, CheckerInterface
{
    private readonly Level $businessEventHandlerLevel;

    private string $url = 'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks#logging';

    public function __construct(
        #[Autowire(service: 'monolog.handler.business_event_handler_buffer')]
        AbstractHandler $businessEventHandlerLevel
    ) {
        $this->businessEventHandlerLevel = $businessEventHandlerLevel->getLevel();
    }

    public function collect(HealthCollection $collection): void
    {
        if (!$this->businessEventHandlerLevel->isHigherThan(Level::Warning)) {
            $collection->add(SettingsResult::warning(
                'business_logger',
                'BusinessEventHandler logging',
                Logger::toMonologLevel($this->businessEventHandlerLevel)->getName(),
                'min WARNING',
                $this->url
            ));
        }
    }
}
