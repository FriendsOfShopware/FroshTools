<?php

declare(strict_types=1);

use Frosh\Tools\Components\Log\MonologLineParserFactory;
use Frosh\Tools\Components\Log\MonologLineParserInterface;
use Frosh\Tools\Components\Log\MonologLogReaderFactory;
use Frosh\Tools\Components\Log\MonologLogReaderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Frosh\Tools\\', '../../')
        ->exclude('../../{DependencyInjection,Resources,FroshTools.php}');

    $services->set(MonologLineParserInterface::class)
        ->factory([MonologLineParserFactory::class, 'create']);

    $services->set(MonologLogReaderInterface::class)
        ->factory([MonologLogReaderFactory::class, 'create']);
};
