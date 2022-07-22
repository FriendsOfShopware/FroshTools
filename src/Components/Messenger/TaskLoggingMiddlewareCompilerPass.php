<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Messenger;

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class TaskLoggingMiddlewareCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        /** @var IteratorArgument $middlewares */
        $middlewares = $container->getDefinition('messenger.bus.shopware')->getArgument(0);
        $vals = $middlewares->getValues();

        $vals[] = new Reference(TaskLoggingMiddleware::class);
        $middlewares->setValues($vals);
    }
}
