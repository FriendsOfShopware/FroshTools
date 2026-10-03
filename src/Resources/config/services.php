<?php

declare(strict_types=1);

use Frosh\Tools\Components\Apps\AppUrlReachability;
use Frosh\Tools\Controller\AppController;
use Shopware\Core\Framework\Log\Monolog\DoctrineSQLHandler;
use Shopware\Core\Framework\Log\SystemActivitySubscriber as CoreSystemActivitySubscriber;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->load('Frosh\Tools\\', '../../')
        ->exclude('../../{DependencyInjection,Resources,FroshTools.php}');

    // AbstractAppLifecycle has no autowiring alias in core, so wire the concrete service.
    $services->set(AppController::class)
        ->arg('$appLifecycle', service('Shopware\Core\Framework\App\Lifecycle\AppLifecycle'));

    $services->set(AppUrlReachability::class)
        ->arg('$cache', service('cache.app'))
        ->arg('$secret', param('kernel.secret'));

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
};
