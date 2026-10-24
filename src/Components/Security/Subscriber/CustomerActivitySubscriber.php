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
final class CustomerActivitySubscriber extends EntityActivitySubscriber
{
    protected const ENTITY = 'customer';
    protected const FIELDS = ['password'];
    protected const CORE_CREATION = false;
    protected const RECORD_CREATION = false;

    public function __construct(
        #[Autowire(service: 'monolog.logger.system_activity')]
        LoggerInterface $logger,
        ActivityActorResolver $actors,
        private readonly PasswordResetContext $resets,
    ) {
        parent::__construct($logger, $actors);
    }

    protected function action(EntityWriteResult $result, Context $context): ?string
    {
        return $this->resets->matches($result, 'store-api.account.recovery.password')
            ? 'password_reset' : parent::action($result, $context);
    }
}
