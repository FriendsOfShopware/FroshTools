<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class PublicFilesystemChecker implements CheckerInterface
{
    public function __construct(private readonly string $fileSystemType)
    {
    }

    public function collect(HealthCollection $collection): void
    {
        $url = 'https://developer.shopware.com/docs/guides/hosting/infrastructure/filesystem#integrated-adapter-configurations';
        if ($this->fileSystemType !== 'local') {
            $collection->add(
                SettingsResult::ok('filesystem', 'PublicFilesystem is not local',
                    $this->fileSystemType,
                    'not local',
                    $url
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::info('filesystem', 'PublicFilesystem should not be local',
                $this->fileSystemType,
                'not local',
                $url
            )
        );
    }
}
