<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Twig;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Twig\TwigCacheWarmer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\BundleHierarchyBuilder;
use Shopware\Core\Framework\Bundle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Twig\Cache\FilesystemCache;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

#[CoversClass(TwigCacheWarmer::class)]
class TwigCacheWarmerTest extends TestCase
{
    public function testNormalTemplateLoadingReusesTheWarmedCache(): void
    {
        $directory = sys_get_temp_dir() . '/frosh-tools-twig-' . bin2hex(random_bytes(8));
        $filesystem = new Filesystem();

        try {
            $loader = new FilesystemLoader();
            $bundles = [];
            $paths = [];
            $templates = [];
            foreach (['Storefront' => 0, 'TestPlugin' => -1] as $name => $priority) {
                $path = $directory . '/' . $name;
                $views = $path . '/Resources/views';
                $filesystem->mkdir($views . '/storefront');
                $filesystem->dumpFile($views . '/storefront/example.html.twig', $name . ': Hello {{ name }}!');
                $bundle = new class($name, $path, $priority) extends Bundle {
                    public function __construct(string $name, string $path, private readonly int $priority)
                    {
                        $this->name = $name;
                        $this->path = $path;
                    }

                    public function getTemplatePriority(): int
                    {
                        return $this->priority;
                    }
                };
                $bundles[$name] = $bundle;
                $paths[$name] = $views;
                $templates[] = '@' . $name . '/storefront/example.html.twig';
                $loader->addPath($views, $name);
            }

            $kernel = $this->createStub(KernelInterface::class);
            $kernel->method('getBundles')->willReturn($bundles);
            $cache = new FilesystemCache($directory . '/cache/twig');
            $twig = new Environment($loader, ['cache' => $cache, 'auto_reload' => false]);
            $warmer = new TwigCacheWarmer($twig, new \ArrayObject($templates), $kernel);
            $files = $warmer->warmUp($directory . '/cache');

            static::assertCount(2, $files);
            foreach ($files as $file) {
                static::assertFileExists($file);
            }
            static::assertSame($cache, $twig->getCache(false));

            // Build the expected hierarchy with Shopware itself, not the warmer's
            // sorting or hashing algorithm. Only the unrelated app DB query is stubbed.
            $connection = $this->createStub(Connection::class);
            $connection->method('fetchAllAssociativeIndexed')->willReturn([]);
            $hierarchy = (new BundleHierarchyBuilder($kernel, $connection))->buildNamespaceHierarchy([]);
            static::assertSame(['TestPlugin', 'Storefront'], array_keys($hierarchy));

            $autoloaders = [];
            foreach (array_keys(ClassLoader::getRegisteredLoaders()) as $vendorDirectory) {
                $autoloaders[] = $vendorDirectory . '/autoload.php';
            }
            $process = new Process([PHP_BINARY, '-d', 'display_errors=stderr', __DIR__ . '/../../Fixtures/Twig/render-warmed-template.php']);
            $process->setInput(json_encode([
                'autoloaders' => $autoloaders,
                'paths' => $paths,
                'hierarchy' => $hierarchy,
                'cacheDir' => $directory . '/cache/twig',
                'template' => '@Storefront/storefront/example.html.twig',
            ], \JSON_THROW_ON_ERROR));
            $process->mustRun();

            static::assertSame('TestPlugin: Hello Shopware!', $process->getOutput());
        } finally {
            $filesystem->remove($directory);
        }
    }
}
