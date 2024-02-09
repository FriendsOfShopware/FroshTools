<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class QueueConnectionChecker implements CheckerInterface
{
    protected string $connection;

    public function __construct(string $connection)
    {
        $this->connection = $connection;
    }

    public function collect(HealthCollection $collection): void
    {
        $schema = $this->getSchema();

        $id = 'queue.adapter';
        $url = 'https://developer.shopware.com/docs/guides/hosting/infrastructure/message-queue.html#message-queue-on-production-systems';

        if ($schema === 'enqueue') {
            $collection->add(
                SettingsResult::warning(
                    $id,
                    'The default queue storage in database does not scale well with multiple workers',
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

            return;
        }

        $collection->add(
            SettingsResult::ok(
                $id,
                'Configured queue storage is ok for multiple workers',
                $this->getSchema(),
                'redis or rabbitmq',
            )
        );
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
