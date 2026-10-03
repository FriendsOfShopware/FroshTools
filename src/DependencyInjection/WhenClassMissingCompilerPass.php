<?php

declare(strict_types=1);

namespace Frosh\Tools\DependencyInjection;

use Frosh\Tools\DependencyInjection\Attribute\WhenClassMissing;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class WhenClassMissingCompilerPass implements CompilerPassInterface
{
    public const TAG = 'frosh_tools.when_class_missing';

    public static function configure(ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(WhenClassMissing::class, static function (ChildDefinition $definition, WhenClassMissing $attribute): void {
            $definition->addTag(self::TAG, ['class' => $attribute->class]);
        });
    }

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds(self::TAG) as $id => $tags) {
            if (str_starts_with($id, '.')) {
                continue;
            }

            foreach ($tags as $tag) {
                $class = $tag['class'] ?? null;
                if (!\is_string($class) || !class_exists($class)) {
                    continue;
                }

                $this->removeService($container, $id);

                break;
            }
        }
    }

    private function removeService(ContainerBuilder $container, string $id): void
    {
        if ($container->hasDefinition($id)) {
            $container->removeDefinition($id);
        }

        $abstractId = '.abstract.instanceof.' . $id;
        if ($container->hasDefinition($abstractId)) {
            $container->removeDefinition($abstractId);
        }

        foreach (array_keys($container->getDefinitions()) as $definitionId) {
            if (str_starts_with($definitionId, '.instanceof.') && str_ends_with($definitionId, '.' . $id)) {
                $container->removeDefinition($definitionId);
            }
        }
    }
}
