<?php

declare(strict_types=1);

use Frosh\Tools\Controller\AppController;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Frosh\Tools\\', '../../')
        ->exclude('../../{DependencyInjection,Resources,FroshTools.php}');

    // Shopware\Core\Framework\App\Url\AppUrlVerifier only exists on Shopware >= 6.7,
    // so it cannot be autowired and is null on older versions.
    // AbstractAppLifecycle has no autowiring alias in core, so wire the concrete service.
    $services->set(AppController::class)
        ->arg('$appLifecycle', service('Shopware\Core\Framework\App\Lifecycle\AppLifecycle'))
        ->arg('$appUrlVerifier', service('Shopware\Core\Framework\App\Url\AppUrlVerifier')->nullOnInvalid());
};
