<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;

/**
 * @internal
 */
final class IntegrationRoleActivitySubscriber extends EntityActivitySubscriber
{
    protected const ENTITY = 'integration_role';
    protected const FIELDS = ['integrationId', 'aclRoleId'];

    /**
     * @param EntityWriteResult<string|array<string, string>> $result
     * @return array<string, mixed>
     */
    protected function details(EntityWriteResult $result): array
    {
        $key = $result->getPrimaryKey();
        return \is_string($key) ? parent::details($result) : [
            'entityId' => $key['integrationId'] ?? null,
            'roleId' => $key['aclRoleId'] ?? null,
        ];
    }
}
