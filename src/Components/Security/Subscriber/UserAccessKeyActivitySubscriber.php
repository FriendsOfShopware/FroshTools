<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;

/**
 * @internal
 */
final class UserAccessKeyActivitySubscriber extends EntityActivitySubscriber
{
    protected const ENTITY = 'user_access_key';
    protected const FIELDS = ['accessKey', 'secretAccessKey', 'userId'];

    /**
     * @param EntityWriteResult<string|array<string, string>> $result
     * @return array<string, mixed>
     */
    protected function details(EntityWriteResult $result): array
    {
        return [...parent::details($result), 'targetUserId' => $result->getPayload()['userId'] ?? null];
    }
}
