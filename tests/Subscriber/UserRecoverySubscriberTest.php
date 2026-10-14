<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Subscriber;

use Frosh\Tools\Subscriber\UserRecoverySubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[CoversClass(UserRecoverySubscriber::class)]
final class UserRecoverySubscriberTest extends TestCase
{
    public function testEnabledKeepsOriginalController(): void
    {
        $subscriber = new UserRecoverySubscriber(true);
        $originalCalled = false;
        $event = $this->createEvent(static function () use (&$originalCalled): Response {
            $originalCalled = true;

            return new Response('original');
        });

        $subscriber->onCreateUserRecovery($event);
        $subscriber->onCheckUserRecovery($event);
        $subscriber->onUpdateUserPassword($event);

        $controller = $event->getController();
        $response = $controller();

        static::assertTrue($originalCalled);
        static::assertSame('original', $response->getContent());
    }

    public function testDisabledCreateReturnsEmptyOkResponse(): void
    {
        $subscriber = new UserRecoverySubscriber(false);
        $event = $this->createEvent();

        $subscriber->onCreateUserRecovery($event);

        $controller = $event->getController();
        $response = $controller();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('', $response->getContent());
    }

    public function testDisabledCheckReturnsBadRequest(): void
    {
        $subscriber = new UserRecoverySubscriber(false);
        $event = $this->createEvent();

        $subscriber->onCheckUserRecovery($event);

        $controller = $event->getController();
        $response = $controller();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testDisabledUpdateReturnsBadRequest(): void
    {
        $subscriber = new UserRecoverySubscriber(false);
        $event = $this->createEvent();

        $subscriber->onUpdateUserPassword($event);

        $controller = $event->getController();
        $response = $controller();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * @param callable(): Response $controller
     */
    private function createEvent(?callable $controller = null): ControllerEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new ControllerEvent(
            $kernel,
            $controller ?? static fn (): Response => new Response('original'),
            new Request(),
            null
        );
    }
}
