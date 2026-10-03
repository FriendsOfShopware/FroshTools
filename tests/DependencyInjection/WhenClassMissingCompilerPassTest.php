<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\DependencyInjection;

use Frosh\Tools\DependencyInjection\Attribute\WhenClassMissing;
use Frosh\Tools\DependencyInjection\WhenClassMissingCompilerPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\AttributeAutoconfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveInstanceofConditionalsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(WhenClassMissingCompilerPass::class)]
final class WhenClassMissingCompilerPassTest extends TestCase
{
    public function testRemovesTaggedServiceWhenClassExists(): void
    {
        $container = $this->container();
        $this->register($container, RemoveWhenPresentSubscriber::class);

        (new WhenClassMissingCompilerPass())->process($container);

        static::assertFalse($container->hasDefinition(RemoveWhenPresentSubscriber::class));
    }

    public function testRemovesAutoconfigureParentsWithTheService(): void
    {
        $container = $this->container();
        $id = RemoveWhenPresentSubscriber::class;
        $this->register($container, $id);
        $container->register('.abstract.instanceof.' . $id, $id)->setAbstract(true);
        $instanceofId = '.instanceof.Symfony\Component\EventDispatcher\EventSubscriberInterface.0.' . $id;
        $instanceof = new ChildDefinition('.abstract.instanceof.' . $id);
        $instanceof->setClass($id);
        $container->setDefinition($instanceofId, $instanceof);

        (new WhenClassMissingCompilerPass())->process($container);

        static::assertFalse($container->hasDefinition($id));
        static::assertFalse($container->hasDefinition('.abstract.instanceof.' . $id));
        static::assertFalse($container->hasDefinition($instanceofId));
        (new ResolveChildDefinitionsPass())->process($container);
    }

    public function testKeepsTaggedServiceWhenClassIsMissing(): void
    {
        $container = $this->container();
        $this->register($container, KeepWhenMissingSubscriber::class);

        (new WhenClassMissingCompilerPass())->process($container);

        static::assertTrue($container->hasDefinition(KeepWhenMissingSubscriber::class));
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        WhenClassMissingCompilerPass::configure($container);

        return $container;
    }

    private function register(ContainerBuilder $container, string $class): void
    {
        $container->register($class, $class)->setAutoconfigured(true);
        (new AttributeAutoconfigurationPass())->process($container);
        (new ResolveInstanceofConditionalsPass())->process($container);
    }
}

#[WhenClassMissing(WhenClassMissingCompilerPassTest::class)]
final class RemoveWhenPresentSubscriber
{
}

#[WhenClassMissing('Frosh\\Tools\\Tests\\DependencyInjection\\MissingCoreSubscriber')]
final class KeepWhenMissingSubscriber
{
}
