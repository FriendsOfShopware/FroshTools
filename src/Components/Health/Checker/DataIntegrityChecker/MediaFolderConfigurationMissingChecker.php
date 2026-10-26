<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class MediaFolderConfigurationMissingChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'media-folder-configuration-missing';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT media_folder.id
        FROM media_folder
        LEFT JOIN media_folder_configuration
            ON media_folder_configuration.id = media_folder.media_folder_configuration_id
        WHERE media_folder.media_folder_configuration_id IS NOT NULL
          AND media_folder_configuration.id IS NULL
        SQL;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function collect(HealthCollection $collection): void
    {
        $count = (int) $this->connection->fetchOne(\sprintf('SELECT COUNT(*) FROM (%s) affected', self::AFFECTED_QUERY));

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Media folders with a deleted folder configuration',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery($this->connection, 'media_folder', self::AFFECTED_QUERY, [], [], $limit, $offset);
    }
}
