<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Subscriber;

use Frosh\Tools\Components\Security\Activity\ActivityActorResolver;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Shared entity-write processing for the dedicated activity subscribers.
 */
abstract class EntityActivitySubscriber implements EventSubscriberInterface
{
    protected const ENTITY = '';
    protected const FIELDS = [];
    protected const RECORD_CREATION = true;
    protected const CORE_CREATION = false;

    public function __construct(
        #[Autowire(service: 'monolog.logger.system_activity')]
        private readonly LoggerInterface $logger,
        private readonly ActivityActorResolver $actors,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [static::ENTITY . '.written' => 'onEntityWritten', static::ENTITY . '.deleted' => 'onEntityWritten'];
    }

    public function onEntityWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $result) {
            $action = $this->action($result, $event->getContext());
            $changed = array_values(array_intersect(static::FIELDS, array_keys($result->getPayload())));
            if ($action === null || ($action === 'update' && $changed === [])) {
                continue;
            }
            $this->logger->info(static::ENTITY . ':' . $action, array_filter([
                ...($action === 'password_reset' ? ['actorType' => 'recovery'] : $this->actors->resolve($event->getContext())),
                ...$this->details($result),
                // Match core creation metadata; changed fields describe subsequent changes.
                ...($action === 'create' && static::CORE_CREATION ? [] : ['changedFields' => $action === 'delete' ? [] : $changed]),
            ], static fn (mixed $value): bool => $value !== null));
        }
    }

    protected function action(EntityWriteResult $result, Context $context): ?string
    {
        return match ($result->getOperation()) {
            EntityWriteResult::OPERATION_INSERT => static::RECORD_CREATION
                && !(static::CORE_CREATION && class_exists('Shopware\\Core\\Framework\\Log\\SystemActivitySubscriber')) ? 'create' : null,
            EntityWriteResult::OPERATION_UPDATE => 'update',
            EntityWriteResult::OPERATION_DELETE => 'delete',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function details(EntityWriteResult $result): array
    {
        return ['entityId' => $result->getPrimaryKey()];
    }
}
