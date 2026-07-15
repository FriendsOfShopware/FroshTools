<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Controller\QueueController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;

#[CoversClass(QueueController::class)]
class QueueControllerTest extends TestCase
{
    #[DataProvider('mutationMethods')]
    public function testQueueMutationsRequireUpdatePrivilege(string $method): void
    {
        $attributes = (new \ReflectionMethod(QueueController::class, $method))->getAttributes(Route::class);

        static::assertCount(1, $attributes);
        static::assertSame(['_acl' => ['frosh_tools:update']], $attributes[0]->newInstance()->defaults);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function mutationMethods(): iterable
    {
        yield 'retry one message' => ['retryMessage'];
        yield 'delete one message' => ['deleteMessage'];
        yield 'purge transport' => ['purgeTransport'];
        yield 'reset all queues' => ['resetQueue'];
    }
}
