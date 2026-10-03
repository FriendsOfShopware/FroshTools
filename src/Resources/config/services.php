<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->load('Frosh\Tools\\', '../../')
        ->exclude('../../{DependencyInjection,Resources,FroshTools.php}');
};
