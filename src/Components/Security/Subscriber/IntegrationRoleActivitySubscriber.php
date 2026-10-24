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
     * @return array<string, mixed>
     */
    protected function details(EntityWriteResult $result): array
    {
        /** @var string|array<string, string> $key */
        $key = $result->getPrimaryKey();

        return \is_string($key) ? parent::details($result) : [
            'entityId' => $key['integrationId'] ?? null,
            'roleId' => $key['aclRoleId'] ?? null,
        ];
    }
}
