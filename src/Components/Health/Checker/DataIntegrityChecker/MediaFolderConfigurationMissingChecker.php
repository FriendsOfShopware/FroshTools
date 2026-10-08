<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class MediaFolderConfigurationMissingChecker implements DataIntegrityCheckerInterface, CheckerInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $count = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM media_folder
                LEFT JOIN media_folder_configuration
                    ON media_folder_configuration.id = media_folder.media_folder_configuration_id
                WHERE media_folder.media_folder_configuration_id IS NOT NULL
                  AND media_folder_configuration.id IS NULL
                SQL,
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'media-folder-configuration-missing',
            'Media folders with a deleted folder configuration',
            $count,
        ));
    }
}
