<?php

declare(strict_types=1);

namespace Frosh\Tools\DependencyInjection;

use Frosh\Tools\DependencyInjection\Attribute\WhenClassMissing;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class WhenClassMissingCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            // Skip autoconfigure parents. Removing only those leaves their instanceof children unresolved.
            if (str_starts_with($id, '.')) {
                continue;
            }

            $class = $this->existingClass($definition->getClass());
            if ($class === null) {
                continue;
            }

            $attribute = $this->whenClassMissing($class);
            if ($attribute === null || !class_exists($attribute->class)) {
                continue;
            }

            $this->removeService($container, $id);
        }
    }

    private function removeService(ContainerBuilder $container, string $id): void
    {
        $container->removeDefinition($id);

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

    /**
     * @return class-string|null
     */
    private function existingClass(?string $class): ?string
    {
        if ($class === null || !class_exists($class)) {
            return null;
        }

        return $class;
    }

    /**
     * @param class-string $class
     */
    private function whenClassMissing(string $class): ?WhenClassMissing
    {
        $attributes = (new \ReflectionClass($class))->getAttributes(WhenClassMissing::class);
        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }
}
