<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;

/**
 * @internal
 */
final class UserRoleActivitySubscriber extends EntityActivitySubscriber
{
    protected const ENTITY = 'acl_user_role';
    protected const FIELDS = ['userId', 'aclRoleId'];

    /**
     * @return array<string, mixed>
     */
    protected function details(EntityWriteResult $result): array
    {
        /** @var string|array<string, string> $key */
        $key = $result->getPrimaryKey();

        return \is_string($key) ? parent::details($result) : [
            'targetUserId' => $key['userId'] ?? null,
            'roleId' => $key['aclRoleId'] ?? null,
        ];
    }
}
