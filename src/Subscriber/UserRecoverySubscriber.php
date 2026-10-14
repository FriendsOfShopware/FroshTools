<?php

declare(strict_types=1);

namespace Frosh\Tools\Subscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

/**
 * Backport of shopware.api.user_recovery.enabled (shopware/shopware#21637)
 * for Shopware versions without core support.
 *
 * When disabled, the Administration "forgot password" flow is blocked by
 * overwriting the controller of the user-recovery routes, so the real
 * controller is never called:
 * - POST /api/_action/user/user-recovery answers with an empty 200 response,
 *   so neither the disabled state nor the existence of an account is revealed.
 * - GET /api/_action/user/user-recovery/hash and
 *   PATCH /api/_action/user/user-recovery/password answer with the generic
 *   400 response, same as an invalid hash on core.
 *
 * Shopware dispatches the ControllerEvent additionally under "<route>.controller"
 * via RouteEventSubscriber, which allows listening on the route name directly.
 */
class UserRecoverySubscriber
{
    public function __construct(
        #[Autowire(param: 'frosh_tools.user_recovery.enabled')]
        private readonly bool $enabled,
    ) {
    }

    #[AsEventListener('api.action.user.user-recovery.controller')]
    public function onCreateUserRecovery(ControllerEvent $event): void
    {
        if ($this->enabled) {
            return;
        }

        // Silently do nothing to avoid revealing that the flow is disabled or whether the email exists.
        $event->setController(static fn (): Response => new Response());
    }

    #[AsEventListener('api.action.user.user-recovery.hash.controller')]
    public function onCheckUserRecovery(ControllerEvent $event): void
    {
        if ($this->enabled) {
            return;
        }

        $event->setController(static fn (): Response => new Response('', Response::HTTP_BAD_REQUEST));
    }

    #[AsEventListener('api.action.user.user-recovery.password.controller')]
    public function onUpdateUserPassword(ControllerEvent $event): void
    {
        if ($this->enabled) {
            return;
        }

        $event->setController(static fn (): Response => new Response('', Response::HTTP_BAD_REQUEST));
    }
}
