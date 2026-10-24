<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Subscriber;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Security\Activity\ActivityActorResolver;
use Frosh\Tools\DependencyInjection\Attribute\WhenClassMissing;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[WhenClassMissing('Shopware\Core\Framework\Log\SystemActivitySubscriber')]
final class AppActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.system_activity')]
        private readonly LoggerInterface $logger,
        private readonly Connection $connection,
        private readonly ActivityActorResolver $actors,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppActivatedEvent::class => 'onAppLifecycle',
            AppDeactivatedEvent::class => 'onAppLifecycle',
            AppInstalledEvent::class => 'onAppLifecycle',
            AppUpdatedEvent::class => 'onAppLifecycle',
            AppDeletedEvent::class => 'onAppDeleted',
        ];
    }

    public function onAppLifecycle(AppActivatedEvent|AppDeactivatedEvent|AppInstalledEvent|AppUpdatedEvent $event): void
    {
        $action = match (true) {
            $event instanceof AppActivatedEvent => 'enable',
            $event instanceof AppDeactivatedEvent => 'disable',
            $event instanceof AppInstalledEvent => 'install',
            $event instanceof AppUpdatedEvent => 'update',
        };

        $app = $event->getApp();
        $this->logger->info('app:' . $action, $this->withoutNullValues([
            'appName' => $app->getName(),
            'appVersion' => $app->getVersion(),
            ...$this->actors->resolve($event->getContext()),
        ]));
    }

    public function onAppDeleted(AppDeletedEvent $event): void
    {
        // Dispatched before the app row is removed, so the name is still readable.
        $app = $this->connection->fetchAssociative(
            'SELECT name, version FROM app WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($event->getAppId())],
        );
        $name = \is_array($app) && \is_string($app['name'] ?? null) ? $app['name'] : null;
        $version = \is_array($app) && \is_string($app['version'] ?? null) ? $app['version'] : null;

        $this->logger->info('app:delete', $this->withoutNullValues([
            'appName' => $name,
            'appVersion' => $version,
            ...($name === null ? ['appId' => $event->getAppId()] : []),
            ...$this->actors->resolve($event->getContext()),
        ]));
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function withoutNullValues(array $context): array
    {
        return array_filter($context, static fn (mixed $value): bool => $value !== null);
    }
}
