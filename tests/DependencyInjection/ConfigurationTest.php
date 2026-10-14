<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\DependencyInjection;

use Frosh\Tools\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

/**
 * @internal
 */
#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    public function testUserRecoveryIsEnabledByDefault(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), []);

        static::assertTrue($config['user_recovery']['enabled']);
    }

    public function testUserRecoveryCanBeDisabled(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [
            ['user_recovery' => ['enabled' => false]],
        ]);

        static::assertFalse($config['user_recovery']['enabled']);
    }
}
