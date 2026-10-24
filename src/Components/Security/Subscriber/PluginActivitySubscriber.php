<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Subscriber;

use Frosh\Tools\Components\Security\Activity\ActivityActorResolver;
use Frosh\Tools\DependencyInjection\Attribute\WhenClassMissing;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[WhenClassMissing('Shopware\Core\Framework\Log\SystemActivitySubscriber')]
final class PluginActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.system_activity')]
        private readonly LoggerInterface $logger,
        private readonly ActivityActorResolver $actors,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginPostActivateEvent::class => 'onPluginLifecycle',
            PluginPostDeactivateEvent::class => 'onPluginLifecycle',
            PluginPostInstallEvent::class => 'onPluginLifecycle',
            PluginPostUninstallEvent::class => 'onPluginLifecycle',
            PluginPostUpdateEvent::class => 'onPluginLifecycle',
        ];
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
            ...$this->actors->resolve($event->getContext()->getContext()),
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
