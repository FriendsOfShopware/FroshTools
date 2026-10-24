<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
final class RecoveryActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.system_activity')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserRecoveryRequestEvent::EVENT_NAME => 'onRecoveryRequested',
            CustomerAccountRecoverRequestEvent::EVENT_NAME => 'onRecoveryRequested',
        ];
    }

    public function onRecoveryRequested(UserRecoveryRequestEvent|CustomerAccountRecoverRequestEvent $event): void
    {
        $isUser = $event instanceof UserRecoveryRequestEvent;
        $targetId = $isUser ? $event->getUserRecovery()->getUserId() : $event->getCustomerId();
        $this->logger->info($isUser ? 'user:recovery_requested' : 'customer:recovery_requested', [
            'actorType' => 'anonymous',
            'entityId' => $targetId,
        ]);
    }
}
