<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Activity;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Exception\ConflictingHeadersException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Captures the existing core/backport channel into structured timeline storage.
 *
 * @internal
 */
class ActivityHandler extends AbstractProcessingHandler
{
    private const CONTEXT_FIELDS = [
        'actorType', 'userId', 'username', 'integrationId', 'integrationAccessKey',
        'loginUsername', 'entityId', 'targetUserId', 'roleId', 'pluginName', 'pluginVersion',
        'previousPluginVersion', 'appName', 'appVersion', 'appId', 'filename', 'statusCode',
    ];

    private const CHANGED_FIELDS = [
        'username', 'email', 'password', 'active', 'admin', 'label', 'accessKey',
        'secretAccessKey', 'deletedAt', 'userId', 'name', 'privileges', 'aclRoleId', 'integrationId',
    ];

    public function __construct(
        private readonly ActivityStore $store,
        #[Autowire(service: 'monolog.logger')]
        private readonly LoggerInterface $fallbackLogger,
        private readonly RequestStack $requestStack,
    ) {
        parent::__construct();
    }

    protected function write(LogRecord $record): void
    {
        if ($record->channel !== 'system_activity' || !preg_match('/^[a-z_]+:[a-z_]+$/D', $record->message) || \strlen($record->message) > 100) {
            return;
        }
        $context = [];
        foreach (self::CONTEXT_FIELDS as $field) {
            $value = $record->context[$field] ?? null;
            if (\is_string($value)) {
                $context[$field] = mb_substr($value, 0, $field === 'actorType' ? 32 : 255);
            } elseif (\is_int($value) || \is_bool($value)) {
                $context[$field] = $value;
            }
        }
        // Derive the address from the main request, never caller-supplied log context.
        // The handler runs immediately, while the request is still on the stack.
        try {
            $clientIp = $this->requestStack->getMainRequest()?->getClientIp();
            if ($clientIp !== null) {
                $context['clientIp'] = $clientIp;
            }
        } catch (ConflictingHeadersException) {
            // Preserve the event without attributing an ambiguous proxy address.
        }
        $changed = $record->context['changedFields'] ?? null;
        if (\is_array($changed)) {
            $context['changedFields'] = array_values(array_intersect(self::CHANGED_FIELDS, array_filter($changed, 'is_string')));
        }

        try {
            $this->store->append($record->message, $record->level->value, $context, \DateTimeImmutable::createFromInterface($record->datetime));
        } catch (\Throwable $exception) {
            // Logging failures must not break login, plugin activation or account recovery.
            // Do not echo event context or exception messages containing SQL parameters.
            $this->fallbackLogger->error('Could not persist FroshTools security activity.', ['exceptionClass' => $exception::class]);

            return;
        }
    }
}
