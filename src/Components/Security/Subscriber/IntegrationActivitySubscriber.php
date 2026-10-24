<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;

/**
 * @internal
 */
final class IntegrationActivitySubscriber extends EntityActivitySubscriber
{
    protected const ENTITY = 'integration';
    protected const FIELDS = ['label', 'accessKey', 'secretAccessKey', 'admin', 'deletedAt'];
    protected const CORE_CREATION = true;

    /**
     * @param EntityWriteResult<string|array<string, string>> $result
     * @return array<string, mixed>
     */
    protected function details(EntityWriteResult $result): array
    {
        return [
            ...parent::details($result),
            'integrationName' => \is_string($result->getPayload()['label'] ?? null) ? $result->getPayload()['label'] : null,
        ];
    }
}
