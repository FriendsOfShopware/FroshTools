<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\DependencyInjection;

use Frosh\Tools\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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

    public function testAcknowledgedChecksDefaultToAnEmptyMap(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), []);

        static::assertSame([], $config['checker']['disabled_checks']);
        static::assertSame([], $config['checker']['acknowledged_checks']);
    }

    public function testAcknowledgedChecksKeepTheCheckIdAndReason(): void
    {
        $reason = 'Ubuntu 24.04 revision 8.3.6-0ubuntu0.24.04.13 contains the PHP 8.3.36 security fixes';
        $config = (new Processor())->processConfiguration(new Configuration(), [
            ['checker' => ['acknowledged_checks' => ['php-version' => $reason]]],
        ]);

        static::assertSame(['php-version' => $reason], $config['checker']['acknowledged_checks']);
    }

    public function testAcknowledgedChecksRejectAnEmptyReason(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [
            ['checker' => ['acknowledged_checks' => ['php-version' => '']]],
        ]);
    }
}
