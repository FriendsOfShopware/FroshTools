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
     * @param EntityWriteResult<string|array<string, string>> $result
     * @return array<string, mixed>
     */
    protected function details(EntityWriteResult $result): array
    {
        $key = $result->getPrimaryKey();
        return \is_string($key) ? parent::details($result) : [
            'targetUserId' => $key['userId'] ?? null,
            'roleId' => $key['aclRoleId'] ?? null,
        ];
    }
}
