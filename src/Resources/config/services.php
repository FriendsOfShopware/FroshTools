<?php

declare(strict_types=1);

use Shopware\Core\Framework\Log\Monolog\DoctrineSQLHandler;
use Shopware\Core\Framework\Log\SystemActivitySubscriber as CoreSystemActivitySubscriber;
use Frosh\Tools\Controller\AppController;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->load('Frosh\Tools\\', '../../')
        ->exclude('../../{DependencyInjection,Resources,FroshTools.php}');

    // Shopware\Core\Framework\App\Url\AppUrlVerifier only exists on Shopware >= 6.7,
    // so it cannot be autowired and is null on older versions.
    // AbstractAppLifecycle has no autowiring alias in core, so wire the concrete service.
    $services->set(AppController::class)
        ->arg('$appLifecycle', service('Shopware\Core\Framework\App\Lifecycle\AppLifecycle'))
        ->arg('$appUrlVerifier', service('Shopware\Core\Framework\App\Url\AppUrlVerifier')->nullOnInvalid());

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
