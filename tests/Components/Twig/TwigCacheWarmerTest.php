<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Twig;

use Frosh\Tools\Components\Twig\TwigCacheWarmer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\ConfigurableFilesystemCache;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Cache\FilesystemCache;
use Twig\Environment;

#[CoversClass(TwigCacheWarmer::class)]
class TwigCacheWarmerTest extends TestCase
{
    public function testCacheKeyMatchesTheInstalledShopwareVersion(): void
    {
        $directory = sys_get_temp_dir() . '/frosh-tools-twig-' . bin2hex(random_bytes(8));
        $originalCache = new FilesystemCache($directory);
        $twig = $this->createMock(Environment::class);
        $twig->method('getCache')->willReturn($originalCache);
        $caches = [];
        $twig->expects(static::exactly(2))->method('setCache')->willReturnCallback(static function ($cache) use (&$caches): void {
            $caches[] = $cache;
        });
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getBundles')->willReturn([]);
        $templates = new class implements \IteratorAggregate {
            public function getIterator(): \Traversable
            {
                return new \ArrayIterator([]);
            }
        };

        $warmer = new TwigCacheWarmer($twig, $templates, $kernel);
        static::assertSame([], $warmer->warmUp($directory));
        static::assertInstanceOf(ConfigurableFilesystemCache::class, $caches[0]);
        $configHash = class_exists(Hasher::class) ? Hasher::hash([]) : md5('[]');
        $expectedCache = new ConfigurableFilesystemCache($directory . '/twig');
        $expectedCache->setConfigHash($configHash);
        $expectedCache->setTemplateScopes(['default']);
        static::assertSame($expectedCache->generateKey('template', 'template_class'), $caches[0]->generateKey('template', 'template_class'));
        static::assertSame($originalCache, $caches[1]);
    }
}
