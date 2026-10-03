<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\DependencyInjection;

use Frosh\Tools\DependencyInjection\Attribute\WhenClassMissing;
use Frosh\Tools\DependencyInjection\WhenClassMissingCompilerPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(WhenClassMissingCompilerPass::class)]
final class WhenClassMissingCompilerPassTest extends TestCase
{
    public function testRemovesServiceWhenClassExists(): void
    {
        $container = new ContainerBuilder();
        $container->register(RemoveWhenPresentSubscriber::class, RemoveWhenPresentSubscriber::class);

        (new WhenClassMissingCompilerPass())->process($container);

        static::assertFalse($container->hasDefinition(RemoveWhenPresentSubscriber::class));
    }

    public function testRemovesAutoconfigureParentsWithTheService(): void
    {
        $container = new ContainerBuilder();
        $id = RemoveWhenPresentSubscriber::class;
        $container->register($id, $id);
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

    public function testKeepsServiceWhenClassIsMissing(): void
    {
        $container = new ContainerBuilder();
        $container->register(KeepWhenMissingSubscriber::class, KeepWhenMissingSubscriber::class);

        (new WhenClassMissingCompilerPass())->process($container);

        static::assertTrue($container->hasDefinition(KeepWhenMissingSubscriber::class));
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
