<?php

declare(strict_types=1);

use Shopware\Core\Framework\Log\Monolog\DoctrineSQLHandler;
use Shopware\Core\Framework\Log\SystemActivitySubscriber as CoreSystemActivitySubscriber;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Frosh\Tools\\', '../../')
        ->exclude('../../{Backport,DependencyInjection,Resources,FroshTools.php}');

    if (class_exists(CoreSystemActivitySubscriber::class)) {
        return;
    }

    $container->extension('monolog', [
        'channels' => ['system_activity'],
        'handlers' => [
            'frosh_tools_system_activity_buffer' => [
                'type' => 'buffer',
                'handler' => 'frosh_tools_system_activity',
                'channels' => ['system_activity'],
            ],
            'frosh_tools_system_activity' => [
                'type' => 'service',
                'id' => DoctrineSQLHandler::class,
                'channels' => ['system_activity'],
            ],
        ],
    ]);

    $services->load('Frosh\Tools\Backport\\', '../../Backport/');
};
