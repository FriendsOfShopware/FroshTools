<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\DependencyInjection;

use Frosh\Tools\DependencyInjection\Attribute\WhenClassMissing;
use Frosh\Tools\DependencyInjection\WhenClassMissingCompilerPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        $container = $this->container();
        $container->register(RemoveWhenPresentSubscriber::class, RemoveWhenPresentSubscriber::class);

        (new WhenClassMissingCompilerPass())->process($container);

        static::assertFalse($container->hasDefinition(RemoveWhenPresentSubscriber::class));
        static::assertFalse($container->hasDefinition('monolog.handler.frosh_tools_system_activity_buffer'));
    }

    public function testRemovesAutoconfigureParentsWithTheService(): void
    {
        $container = $this->container();
        $id = RemoveWhenPresentSubscriber::class;
        $container->register($id, $id);
        $container->register('.abstract.instanceof.'.$id, $id)->setAbstract(true);
        $instanceofId = '.instanceof.Symfony\Component\EventDispatcher\EventSubscriberInterface.0.'.$id;
        $instanceof = new ChildDefinition('.abstract.instanceof.'.$id);
        $instanceof->setClass($id);
        $container->setDefinition($instanceofId, $instanceof);

        (new WhenClassMissingCompilerPass())->process($container);

        static::assertFalse($container->hasDefinition($id));
        static::assertFalse($container->hasDefinition('.abstract.instanceof.'.$id));
        static::assertFalse($container->hasDefinition($instanceofId));
        (new ResolveChildDefinitionsPass())->process($container);
    }

    public function testKeepsServiceAndRegistersActivityLoggerWhenClassIsMissing(): void
    {
        $container = $this->container();
        $container->register(KeepWhenMissingSubscriber::class, KeepWhenMissingSubscriber::class);

        (new WhenClassMissingCompilerPass())->process($container);

        static::assertTrue($container->hasDefinition(KeepWhenMissingSubscriber::class));
        static::assertTrue($container->hasAlias('monolog.handler.frosh_tools_system_activity'));
        static::assertTrue($container->hasDefinition('monolog.handler.frosh_tools_system_activity_buffer'));
        static::assertSame(
            ['monolog.handler.frosh_tools_system_activity_buffer' => ['type' => 'inclusive', 'elements' => ['system_activity']]],
            $container->getParameter('monolog.handlers_to_channels'),
        );
        static::assertSame(['system_activity'], $container->getParameter('monolog.additional_channels'));
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('monolog.handlers_to_channels', []);
        $container->setParameter('monolog.additional_channels', []);

        return $container;
    }
}

#[WhenClassMissing(WhenClassMissingCompilerPassTest::class)]
final class RemoveWhenPresentSubscriber
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.system_activity')]
        private readonly LoggerInterface $logger,
    ) {
    }
}

#[WhenClassMissing('Frosh\\Tools\\Tests\\DependencyInjection\\MissingCoreSubscriber')]
final class KeepWhenMissingSubscriber
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.system_activity')]
        private readonly LoggerInterface $logger,
    ) {
    }
}
