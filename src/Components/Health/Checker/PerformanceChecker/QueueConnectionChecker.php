<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class QueueConnectionChecker implements PerformanceCheckerInterface, CheckerInterface
{
    private const ID = 'queue.adapter';
    private const URL = 'https://developer.shopware.com/docs/guides/hosting/infrastructure/message-queue.html#message-queue-on-production-systems';
    private const RECOMMENDED = 'redis or rabbitmq';

    public function __construct(
        #[Autowire(param: 'frosh_tools.queue_connection')]
        protected string $connection,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $schema = $this->getSchema();
        $state = $this->determineState($schema);
        $snippet = $this->getSnippet($schema);

        $collection->add(
            SettingsResult::create(
                $state,
                self::ID,
                $snippet,
                $schema,
                self::RECOMMENDED,
                self::URL,
            ),
        );
    }

    private function determineState(string $schema): string
    {
        return match ($schema) {
            'redis', 'rabbitmq' => 'ok',
            'doctrine', 'sync' => 'warning',
            default => 'info',
        };
    }

    private function getSnippet(string $schema): string
    {
        return match ($schema) {
            'doctrine' => 'The queue storage in database does not scale well with multiple workers',
            'sync' => 'The sync queue is not suitable for production environments',
            'redis', 'rabbitmq' => '',
            default => 'Unknown queue adapter',
        };
    }

    private function getSchema(): string
    {
        $urlSchema = \parse_url($this->connection, \PHP_URL_SCHEME);

        if (!\is_string($urlSchema)) {
            $urlSchema = explode('://', $this->connection)[0];
        }

        return $urlSchema;
    }
}
