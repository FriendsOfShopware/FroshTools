<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Subscriber;

use Frosh\Tools\Components\Security\Activity\ActivityActorResolver;
use Frosh\Tools\Components\Security\Activity\PasswordResetContext;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @internal
 */
final class UserActivitySubscriber extends EntityActivitySubscriber
{
    protected const ENTITY = 'user';
    protected const FIELDS = ['username', 'email', 'password', 'active', 'admin'];
    protected const CORE_CREATION = true;
    protected const RECORD_CREATION = true;

    public function __construct(
        #[Autowire(service: 'monolog.logger.system_activity')]
        LoggerInterface $logger,
        ActivityActorResolver $actors,
        private readonly PasswordResetContext $resets,
    ) {
        parent::__construct($logger, $actors);
    }

    /** @param EntityWriteResult<string|array<string, string>> $result */
    protected function action(EntityWriteResult $result, Context $context): ?string
    {
        return $this->resets->matches($result, 'api.action.user.user-recovery.password')
            ? 'password_reset' : parent::action($result, $context);
    }
}
