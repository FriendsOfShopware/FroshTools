<?php

namespace Frosh\Tools\Tests\unit\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\PerformanceChecker\QueueConnectionChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Test\SettingsResultAssertionHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueueConnectionChecker::class)]
class QueueConnectionCheckerTest extends TestCase
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function connectionProvider(): array
    {
        return [
            'doctrine connection' => [
                'connection' => 'doctrine://default',
                'expectWarning' => true,
                'schema' => 'doctrine',
                'message' => 'The queue storage in database does not scale well with multiple workers',
                'recommended' => 'redis or rabbitmq',
            ],
            'sync connection' => [
                'connection' => 'sync://',
                'expectWarning' => true,
                'schema' => 'sync',
                'message' => 'The sync queue is not suitable for production environments',
                'recommended' => 'redis or rabbitmq',
            ],
            'redis connection' => [
                'connection' => 'redis://localhost:6379/queue',
                'expectWarning' => false,
                'schema' => 'redis',
                'message' => '',
                'recommended' => '',
            ],
            'rabbitmq connection' => [
                'connection' => 'amqp://guest:guest@localhost:5672/%2f/messages',
                'expectWarning' => false,
                'schema' => 'amqp',
                'message' => '',
                'recommended' => '',
            ],
            'sqs connection' => [
                'connection' => 'sqs://default',
                'expectWarning' => false,
                'schema' => 'sqs',
                'message' => '',
                'recommended' => '',
            ],
            'connection without standard URL format' => [
                'connection' => 'redis://localhost',
                'expectWarning' => false,
                'schema' => 'redis',
                'message' => '',
                'recommended' => '',
            ],
        ];
    }
    
    #[DataProvider('connectionProvider')]
    public function testCollect(string $connection, bool $expectWarning, string $schema, string $message, string $recommended): void
    {
        // Create the checker with our test connection
        $checker = new QueueConnectionChecker($connection);
        
        // Create a health collection
        $collection = new HealthCollection();
        
        // Call the collect method
        $checker->collect($collection);
        
        if ($expectWarning) {
            // Assert that a warning was added to the collection
            SettingsResultAssertionHelper::assertSingleSettingsResult(
                $collection,
                'queue.adapter',
                SettingsResult::WARNING,
                $schema,
                $recommended,
                'https://developer.shopware.com/docs/guides/hosting/infrastructure/message-queue.html#message-queue-on-production-systems'
            );
            
            // Get the warning and check its snippet (message)
            $warning = SettingsResultAssertionHelper::assertSettingsResultExists($collection, 'queue.adapter');
            $this->assertEquals($message, $warning->snippet);
        } else {
            // Assert that no warning was added to the collection
            SettingsResultAssertionHelper::assertSettingsResultCount($collection, 0);
        }
    }
    
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function malformedConnectionProvider(): array
    {
        return [
            'malformed connection with colon' => [
                'connection' => 'malformed:connection',
                'expectedSchema' => 'malformed',
            ],
            'malformed connection without colon' => [
                'connection' => 'malformedconnection',
                'expectedSchema' => 'malformedconnection',
            ],
        ];
    }
    
    #[DataProvider('malformedConnectionProvider')]
    public function testGetSchemaWithMalformedConnection(string $connection, string $expectedSchema): void
    {
        // Create the checker with our test connection
        $checker = new QueueConnectionChecker($connection);
        
        // Create a health collection
        $collection = new HealthCollection();
        
        // Call the collect method
        $checker->collect($collection);
        
        // No warnings should be added for non-doctrine, non-sync connections
        SettingsResultAssertionHelper::assertSettingsResultCount($collection, 0);
    }
}
