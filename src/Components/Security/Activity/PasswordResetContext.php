<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Activity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
final class PasswordResetContext
{
    public function __construct(private readonly RequestStack $requests)
    {
    }

    /** @param EntityWriteResult<string|array<string, string>> $result */
    public function matches(EntityWriteResult $result, string $route): bool
    {
        return $result->getOperation() === EntityWriteResult::OPERATION_UPDATE
            && array_key_exists('password', $result->getPayload())
            && $this->requests->getMainRequest()?->attributes->get('_route') === $route;
    }
}
