<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\DependencyInjection;

use Frosh\Tools\DependencyInjection\FroshToolsExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(FroshToolsExtension::class)]
final class FroshToolsExtensionTest extends TestCase
{
    public function testAcknowledgedChecksParameterDefaultsToAnEmptyMap(): void
    {
        $container = new ContainerBuilder();
        (new FroshToolsExtension())->load([], $container);

        static::assertSame([], $container->getParameter('frosh_tools.checker.disabled_checks'));
        static::assertSame([], $container->getParameter('frosh_tools.checker.acknowledged_checks'));
    }
}
