<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Subscriber;

use Doctrine\DBAL\Connection;
use Frosh\Tools\DependencyInjection\Attribute\WhenClassMissing;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Logs system activity on Shopware versions that do not ship the core subscriber.
 *
 * @internal
 */
#[WhenClassMissing('Shopware\Core\Framework\Log\SystemActivitySubscriber')]
final class SystemActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.system_activity')]
        private readonly LoggerInterface $logger,
        private readonly Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'user.written' => 'onEntityWritten',
            'integration.written' => 'onEntityWritten',
            PluginPostActivateEvent::class => 'onPluginLifecycle',
            PluginPostDeactivateEvent::class => 'onPluginLifecycle',
            PluginPostInstallEvent::class => 'onPluginLifecycle',
            PluginPostUninstallEvent::class => 'onPluginLifecycle',
            PluginPostUpdateEvent::class => 'onPluginLifecycle',
            AppActivatedEvent::class => 'onAppLifecycle',
            AppDeactivatedEvent::class => 'onAppLifecycle',
            AppInstalledEvent::class => 'onAppLifecycle',
            AppUpdatedEvent::class => 'onAppLifecycle',
            AppDeletedEvent::class => 'onAppDeleted',
        ];
    }

    public function onEntityWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $result) {
            if ($result->getOperation() !== EntityWriteResult::OPERATION_INSERT) {
                continue;
            }

            $this->logger->info($event->getEntityName() . ':create', $this->withoutNullValues([
                'entityId' => $result->getPrimaryKey(),
                ...$this->actor($event->getContext()),
            ]));
        }
    }

    public function onPluginLifecycle(PluginPostActivateEvent|PluginPostDeactivateEvent|PluginPostInstallEvent|PluginPostUninstallEvent|PluginPostUpdateEvent $event): void
    {
        $action = match (true) {
            $event instanceof PluginPostActivateEvent => 'enable',
            $event instanceof PluginPostDeactivateEvent => 'disable',
            $event instanceof PluginPostInstallEvent => 'install',
            $event instanceof PluginPostUninstallEvent => 'uninstall',
            $event instanceof PluginPostUpdateEvent => 'update',
        };

        $this->logger->info('plugin:' . $action, $this->withoutNullValues([
            'pluginName' => $event->getPlugin()->getName(),
            'pluginVersion' => $event instanceof PluginPostUpdateEvent ? $event->getContext()->getUpdatePluginVersion() : $event->getContext()->getCurrentPluginVersion(),
            ...($event instanceof PluginPostUpdateEvent ? ['previousPluginVersion' => $event->getContext()->getCurrentPluginVersion()] : []),
            ...$this->actor($event->getContext()->getContext()),
        ]));
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
            ...$this->actor($event->getContext()),
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
            ...$this->actor($event->getContext()),
        ]));
    }

    /**
     * @return array{actorType?: string|null, userId?: string|null, username?: string|null, integrationAccessKey?: string|null}
     */
    public function actor(Context $context): array
    {
        $source = $context->getSource();
        if ($source instanceof SystemSource) {
            return ['actorType' => 'system'];
        }
        if (!$source instanceof AdminApiSource) {
            return [];
        }

        $actor = [
            'actorType' => match (true) {
                $source->getUserId() !== null => 'user',
                $source->getIntegrationId() !== null => 'integration',
                default => null,
            },
            'userId' => $source->getUserId(),
            'integrationAccessKey' => null,
        ];
        if ($source->getUserId() !== null) {
            $username = $this->connection->fetchOne('SELECT username FROM `user` WHERE id = :id', [
                'id' => Uuid::fromHexToBytes($source->getUserId()),
            ]);
            $actor['username'] = \is_string($username) ? $username : null;
        }
        if ($source->getIntegrationId() !== null) {
            $accessKey = $this->connection->fetchOne('SELECT access_key FROM integration WHERE id = :id', [
                'id' => Uuid::fromHexToBytes($source->getIntegrationId()),
            ]);
            $actor['integrationAccessKey'] = \is_string($accessKey) ? $accessKey : null;
        }

        return $actor;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function withoutNullValues(array $context): array
    {
        return array_filter($context, static fn (mixed $value): bool => $value !== null);
    }
}
