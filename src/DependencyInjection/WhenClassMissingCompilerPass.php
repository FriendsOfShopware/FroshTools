<?php

declare(strict_types=1);

namespace Frosh\Tools\DependencyInjection;

use Frosh\Tools\DependencyInjection\Attribute\WhenClassMissing;
use Monolog\Handler\BufferHandler;
use Shopware\Core\Framework\Log\Monolog\DoctrineSQLHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class WhenClassMissingCompilerPass implements CompilerPassInterface
{
    private const ACTIVITY_LOGGER = 'monolog.logger.system_activity';

    private const ACTIVITY_HANDLER = 'monolog.handler.frosh_tools_system_activity';

    private const ACTIVITY_BUFFER = 'monolog.handler.frosh_tools_system_activity_buffer';

    public function process(ContainerBuilder $container): void
    {
        $needsActivityLogger = false;

        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $this->existingClass($definition->getClass());
            if ($class === null) {
                continue;
            }

            $attribute = $this->whenClassMissing($class);
            if ($attribute === null) {
                continue;
            }

            if (class_exists($attribute->class)) {
                $container->removeDefinition($id);

                continue;
            }

            if ($this->needsActivityLogger($class)) {
                $needsActivityLogger = true;
            }
        }

        if ($needsActivityLogger) {
            $this->registerActivityLogger($container);
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

    /**
     * @param class-string $class
     */
    private function needsActivityLogger(string $class): bool
    {
        $constructor = (new \ReflectionClass($class))->getConstructor();
        if ($constructor === null) {
            return false;
        }

        foreach ($constructor->getParameters() as $parameter) {
            foreach ($parameter->getAttributes(Autowire::class) as $attribute) {
                if ($this->autowiresActivityLogger($attribute->newInstance())) {
                    return true;
                }
            }
        }

        return false;
    }

    private function autowiresActivityLogger(Autowire $autowire): bool
    {
        // Symfony 6.4 exposes $service. Symfony 7 stores the same target as a Reference in $value.
        $reflection = new \ReflectionObject($autowire);
        if ($reflection->hasProperty('service') && $reflection->getProperty('service')->getValue($autowire) === self::ACTIVITY_LOGGER) {
            return true;
        }

        $value = $autowire->value;
        if ($value instanceof Reference) {
            return (string) $value === self::ACTIVITY_LOGGER;
        }

        return $value === self::ACTIVITY_LOGGER || $value === '@' . self::ACTIVITY_LOGGER;
    }

    private function registerActivityLogger(ContainerBuilder $container): void
    {
        if (!$container->hasAlias(self::ACTIVITY_HANDLER) && !$container->hasDefinition(self::ACTIVITY_HANDLER)) {
            $container->setAlias(self::ACTIVITY_HANDLER, DoctrineSQLHandler::class);
        }

        if (!$container->hasDefinition(self::ACTIVITY_BUFFER)) {
            $buffer = new Definition(BufferHandler::class);
            $buffer->setArguments([new Reference(self::ACTIVITY_HANDLER)]);
            $container->setDefinition(self::ACTIVITY_BUFFER, $buffer);
        }

        $handlers = $container->hasParameter('monolog.handlers_to_channels')
            ? $container->getParameter('monolog.handlers_to_channels')
            : [];
        if (!\is_array($handlers)) {
            $handlers = [];
        }
        $handlers[self::ACTIVITY_BUFFER] = ['type' => 'inclusive', 'elements' => ['system_activity']];
        $container->setParameter('monolog.handlers_to_channels', $handlers);

        $channels = $container->hasParameter('monolog.additional_channels')
            ? $container->getParameter('monolog.additional_channels')
            : [];
        if (!\is_array($channels)) {
            $channels = [];
        }
        if (!\in_array('system_activity', $channels, true)) {
            $channels[] = 'system_activity';
            $container->setParameter('monolog.additional_channels', $channels);
        }
    }
}
