<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class QueueConnectionChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public function __construct(
        #[Autowire('%frosh_tools.queue_connection%')]
        protected string $connection
    ) {}

    public function collect(HealthCollection $collection): void
    {
        if (\str_starts_with($this->connection, 'doctrine://default')) {
            $collection->add(
                SettingsResult::warning(
                    'queue.adapter',
                    'The default queue storage in database does not scale well with multiple workers',
                    'default',
                    'redis or rabbitmq',
                    'https://developer.shopware.com/docs/guides/hosting/infrastructure/message-queue#transport-rabbitmq-example'
                )
            );
        }
    }
}
