<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Activity;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
final class ActivityActorResolver
{
    public function __construct(private readonly Connection $connection)
    {
    }

    private function actorName(AdminApiSource $source): ?string
    {
        try {
            if ($source->getUserId() !== null) {
                $name = $this->connection->fetchOne('SELECT username FROM `user` WHERE id = :id', ['id' => Uuid::fromHexToBytes($source->getUserId())]);
            } elseif ($source->getIntegrationId() !== null) {
                $name = $this->connection->fetchOne('SELECT label FROM integration WHERE id = :id', ['id' => Uuid::fromHexToBytes($source->getIntegrationId())]);
            } else {
                return null;
            }
        } catch (\Throwable) {
            // Keep the known actor ID when an optional display-name lookup fails.
            return null;
        }

        return \is_string($name) ? $name : null;
    }

    /**
     * @return array<string, string|null>
     */
    public function resolve(Context $context): array
    {
        $source = $context->getSource();
        if ($source instanceof SystemSource) {
            return ['actorType' => 'system'];
        }
        if ($source instanceof AdminApiSource) {
            return [
                'actorType' => $source->getUserId() !== null ? 'user' : 'integration',
                'userId' => $source->getUserId(),
                'integrationId' => $source->getIntegrationId(),
                'username' => $this->actorName($source),
            ];
        }

        return ['actorType' => 'unknown'];
    }
}
