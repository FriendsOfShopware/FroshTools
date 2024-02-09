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
        $schema = $this->getSchema();

        $id = 'queue.adapter';
        $url = 'https://developer.shopware.com/docs/guides/hosting/infrastructure/message-queue.html#message-queue-on-production-systems';

        if ($schema === 'doctrine') {
            $collection->add(
                SettingsResult::warning(
                    $id,
                    'The queue storage in database does not scale well with multiple workers',
                    $schema,
                    'redis or rabbitmq',
                    $url
                )
            );

            return;
        }

        if ($schema === 'sync') {
            $collection->add(
                SettingsResult::warning(
                    $id,
                    'The sync queue is not suitable for production environments',
                    $schema,
                    'redis or rabbitmq',
                    $url
                )
            );
        }
    }

    private function getSchema(): string
    {
        $urlSchema = \parse_url($this->connection, \PHP_URL_SCHEME);

        if (!is_string($urlSchema)) {
            $urlSchema = explode('://', $this->connection)[0] ?? 'unknown';
        }

        return $urlSchema;
    }
}
