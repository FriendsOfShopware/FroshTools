<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Subscriber;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
final class LoginActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.system_activity')]
        private readonly LoggerInterface $logger,
        private readonly Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onLoginResponse', -100]];
    }

    public function onLoginResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $parameters = $request->request->all();
        $route = $request->attributes->get('_route');
        if (!$event->isMainRequest() || $route !== 'api.oauth.token'
            || ($parameters['grant_type'] ?? null) !== 'password'
            || ($parameters['client_id'] ?? null) !== 'administration') {
            return;
        }
        $status = $event->getResponse()->getStatusCode();
        if ($status >= 500 || ($status >= 300 && $status < 400)) {
            return; // Server errors are not evidence of rejected credentials.
        }
        $success = $event->getResponse()->isSuccessful();
        $username = $parameters['username'] ?? null;
        $userId = null;
        if ($success && \is_string($username)) {
            try {
                $userId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM `user` WHERE username = ?', [$username]);
            } catch (\Throwable) {
                // Optional activity enrichment must not interrupt a successful login.
            }
        }
        // A submitted identity is explicitly labelled, especially for failed attempts.
        // Never copy request/response bodies, passwords, tokens or user agents.
        // ActivityHandler derives the client IP from the current HTTP request.
        $this->logger->log($success ? 'info' : 'warning', $success ? 'user:login' : 'user:login_failed', [
            ...(\is_string($userId) && $userId !== '' ? ['userId' => $userId] : []),
            'actorType' => $success ? 'user' : 'anonymous',
            'loginUsername' => \is_string($username) ? mb_substr($username, 0, 255) : '',
            'statusCode' => $status,
        ]);
    }
}
