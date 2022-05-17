<?php declare(strict_types=1);

namespace Frosh\Tools\DependencyInjection;

use Frosh\Tools\Components\CacheAdapter;
use Frosh\Tools\Components\CacheRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class CacheCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $cacheRegistry = new Definition(CacheRegistry::class);
        $cacheRegistry->setLazy(true);
        $container->setDefinition(CacheRegistry::class, $cacheRegistry);

        foreach ($container->findTaggedServiceIds('cache.pool') as $id => $config) {
            if ($container->getDefinition($id)->isAbstract()) {
                continue;
            }

            $def = new Definition(CacheAdapter::class);
            $def->addArgument(new Reference($id));

            $cacheRegistry->addMethodCall('addAdapter', [
                $config[0]['name'] ?? $id,
                $def,
            ]);
        }
    }
}
