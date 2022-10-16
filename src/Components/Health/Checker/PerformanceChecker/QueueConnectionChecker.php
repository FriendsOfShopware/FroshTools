<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use function parse_url;
use const PHP_URL_SCHEME;
use function str_starts_with;

class QueueConnectionChecker implements CheckerInterface
{
    protected string $connection;

    public function __construct(string $connection)
    {
        $this->connection = $connection;
    }

    public function collect(HealthCollection $collection): void
    {
        if (str_starts_with($this->connection, 'enqueue://default')) {
            $collection->add(
                SettingsResult::warning('The default queue storage in database does not scale well with multiple workers',
                    'default',
                    'redis or rabbitmq',
                    'https://developer.shopware.com/docs/guides/hosting/infrastructure/message-queue#transport-rabbitmq-example'
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok('Configured queue storage is ok for multiple workers',
                parse_url($this->connection, PHP_URL_SCHEME),
                'redis or rabbitmq',
            )
        );
    }
}
