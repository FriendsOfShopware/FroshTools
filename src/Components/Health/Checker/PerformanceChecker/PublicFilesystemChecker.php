<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class PublicFilesystemChecker implements CheckerInterface
{
    public const PUBLIC_FILE_SYSTEM_NAME = 'Public File System';

    private string $fileSystemType;

    public function __construct(string $fileSystemType)
    {
        $this->fileSystemType = $fileSystemType;
    }

    public function collect(HealthCollection $collection): void
    {
        $url = 'https://developer.shopware.com/docs/guides/hosting/infrastructure/filesystem#integrated-adapter-configurations';
        if ($this->fileSystemType !== 'local') {
            $collection->add(
                SettingsResult::ok('filesystem', self::PUBLIC_FILE_SYSTEM_NAME, 'PublicFilesystem is not local',
                    $this->fileSystemType,
                    'not local',
                    $url
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::info('filesystem', self::PUBLIC_FILE_SYSTEM_NAME, 'PublicFilesystem should not be local',
                $this->fileSystemType,
                'not local',
                $url
            )
        );
    }
}
