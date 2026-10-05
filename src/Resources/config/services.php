<?php

declare(strict_types=1);

use Frosh\Tools\Components\Security\Activity\ActivityCleanupTask;
use Frosh\Tools\Components\Security\Activity\ActivityHandler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->load('Frosh\Tools\\', '../../')
        ->exclude('../../{DependencyInjection,Resources,Migration,FroshTools.php}');

    $container->services()->set(ActivityCleanupTask::class)->tag('shopware.scheduled.task');

    $container->extension('monolog', [
        'channels' => ['system_activity'],
        'handlers' => [
            'frosh_tools_system_activity' => [
                'type' => 'service',
                'id' => ActivityHandler::class,
                'channels' => ['system_activity'],
            ],
        ],
    ]);
};
