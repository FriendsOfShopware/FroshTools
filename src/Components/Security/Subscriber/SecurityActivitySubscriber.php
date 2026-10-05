<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Subscriber;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Complements core and the legacy activity backport without repeating their creation events.
 *
 * @internal
 */
final class SecurityActivitySubscriber implements EventSubscriberInterface
{
    private const FIELDS = [
        'customer' => ['password'],
        'user' => ['username', 'email', 'password', 'active', 'admin'],
        'integration' => ['label', 'accessKey', 'secretAccessKey', 'admin', 'deletedAt'],
        'user_access_key' => ['accessKey', 'secretAccessKey', 'userId'],
        'acl_role' => ['name', 'privileges', 'deletedAt'],
        'acl_user_role' => ['userId', 'aclRoleId'],
        'integration_role' => ['integrationId', 'aclRoleId'],
    ];

    public function __construct(
        #[Autowire(service: 'monolog.logger.system_activity')]
        private readonly LoggerInterface $logger,
        private readonly Connection $connection,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        $events = [
            KernelEvents::RESPONSE => ['onLoginResponse', -100],
            UserRecoveryRequestEvent::EVENT_NAME => 'onRecoveryRequested',
            CustomerAccountRecoverRequestEvent::EVENT_NAME => 'onRecoveryRequested',
        ];
        foreach (array_keys(self::FIELDS) as $entity) {
            $events[$entity . '.written'] = 'onEntityWritten';
            $events[$entity . '.deleted'] = 'onEntityWritten';
        }

        return $events;
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

    /**
     * @param EntityWrittenEvent<string|array<string, string>> $event
     */
    public function onEntityWritten(EntityWrittenEvent $event): void
    {
        $entity = $event->getEntityName();
        $fields = self::FIELDS[$entity] ?? null;
        if ($fields === null) {
            return;
        }
        foreach ($event->getWriteResults() as $result) {
            $operation = $result->getOperation();
            if ($operation === EntityWriteResult::OPERATION_INSERT && \in_array($entity, ['user', 'integration', 'customer'], true)) {
                continue; // Already supplied by core or SystemActivitySubscriber.
            }
            $action = match ($operation) {
                EntityWriteResult::OPERATION_INSERT => 'create',
                EntityWriteResult::OPERATION_UPDATE => 'update',
                EntityWriteResult::OPERATION_DELETE => 'delete',
                default => null,
            };
            $changedFields = array_values(array_intersect($fields, array_keys($result->getPayload())));
            if ($action === null || ($action === 'update' && $changedFields === [])) {
                continue; // Ignore usage timestamps and unrelated profile preferences.
            }
            $resetRoute = match ($entity) {
                'user' => 'api.action.user.user-recovery.password',
                'customer' => 'store-api.account.recovery.password',
                default => null,
            };
            $isReset = $action === 'update' && \in_array('password', $changedFields, true)
                && $resetRoute !== null && $this->requestStack->getMainRequest()?->attributes->get('_route') === $resetRoute;
            if ($isReset) {
                $action = 'password_reset';
            }
            $primaryKey = $result->getPrimaryKey();
            $details = \is_string($primaryKey) ? ['entityId' => $primaryKey] : [
                'targetUserId' => $primaryKey['userId'] ?? null,
                'roleId' => $primaryKey['aclRoleId'] ?? null,
                'entityId' => $primaryKey['integrationId'] ?? null,
            ];
            $this->logger->info($entity . ':' . $action, array_filter([
                ...($isReset ? ['actorType' => 'recovery'] : $this->actor($event->getContext())),
                ...$details,
                'changedFields' => $action === 'delete' ? [] : $changedFields,
            ], static fn (mixed $value): bool => $value !== null));
        }
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
        $userId = $success && \is_string($username)
            ? $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM `user` WHERE username = ?', [$username])
            : null;
        // A submitted identity is explicitly labelled, especially for failed attempts.
        // Never copy request/response bodies, passwords, tokens or user agents.
        // ActivityHandler derives the client IP from the current HTTP request.
        $this->logger->log($success ? 'info' : 'warning', $success ? 'user:login' : 'user:login_failed', [
            ...($userId ? ['userId' => $userId] : []),
            'actorType' => $success ? 'user' : 'anonymous',
            'loginUsername' => \is_string($username) ? mb_substr($username, 0, 255) : '',
            'statusCode' => $status,
        ]);
    }

    private function actorName(AdminApiSource $source): ?string
    {
        if ($source->getUserId() !== null) {
            $name = $this->connection->fetchOne('SELECT username FROM `user` WHERE id = :id', ['id' => Uuid::fromHexToBytes($source->getUserId())]);
        } elseif ($source->getIntegrationId() !== null) {
            $name = $this->connection->fetchOne('SELECT label FROM integration WHERE id = :id', ['id' => Uuid::fromHexToBytes($source->getIntegrationId())]);
        } else {
            return null;
        }

        return \is_string($name) ? $name : null;
    }

    /**
     * @return array<string, string|null>
     */
    private function actor(Context $context): array
    {
        $source = $context->getSource();
        if ($source instanceof SystemSource) {
            return ['actorType' => 'system'];
        }
        if ($source instanceof AdminApiSource) {
            return [
                'actorType' => $source->getUserId() !== null ? 'user' : 'integration',
                'userId' => $source->getUserId(),
                'integrationId' => $source->getIntegrationId(),
                'username' => $this->actorName($source),
            ];
        }

        return ['actorType' => 'unknown'];
    }
}
