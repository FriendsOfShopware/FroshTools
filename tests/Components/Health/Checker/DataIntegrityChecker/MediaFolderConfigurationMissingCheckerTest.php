<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\MediaFolderConfigurationMissingChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(MediaFolderConfigurationMissingChecker::class)]
class MediaFolderConfigurationMissingCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'media-folder-configuration-missing';

    private MediaFolderConfigurationMissingChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(MediaFolderConfigurationMissingChecker::class);
        $this->connection->executeStatement(
            'UPDATE media_folder
            LEFT JOIN media_folder_configuration ON media_folder_configuration.id = media_folder.media_folder_configuration_id
            SET media_folder.media_folder_configuration_id = NULL
            WHERE media_folder_configuration.id IS NULL'
        );
    }

    public function testReportsOkForFoldersWithExistingOrWithoutConfiguration(): void
    {
        $configurationId = Uuid::randomBytes();
        $this->connection->insert('media_folder_configuration', [
            'id' => $configurationId,
            'created_at' => self::now(),
        ]);

        $this->createFolder($configurationId);
        $this->createFolder(null);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsFoldersWithADeletedConfiguration(): void
    {
        $this->createFolder(Uuid::randomBytes());
        $this->createFolder(Uuid::randomBytes());

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }

    private function createFolder(?string $configurationId): void
    {
        $this->connection->insert('media_folder', [
            'id' => Uuid::randomBytes(),
            'name' => 'Frosh data integrity check folder',
            'media_folder_configuration_id' => $configurationId,
            'created_at' => self::now(),
        ]);
    }
}
