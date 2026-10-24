<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Activity;

use Doctrine\DBAL\Connection;
use Monolog\LogRecord;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * @internal
 */
#[AutoconfigureTag('monolog.processor', ['channel' => 'system_activity'])]
final class IntegrationActivityProcessor
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $id = $record->context['entityId'] ?? null;
        if (!str_starts_with($record->message, 'integration:') || isset($record->context['integrationName'])
            || !\is_string($id) || !Uuid::isValid($id)) {
            return $record;
        }
        try {
            $name = $this->connection->fetchOne('SELECT label FROM integration WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        } catch (\Throwable) {
            return $record; // Name enrichment must not prevent recording the event.
        }

        return \is_string($name) ? $record->with(context: [...$record->context, 'integrationName' => $name]) : $record;
    }
}
