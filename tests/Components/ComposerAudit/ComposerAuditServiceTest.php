<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\ComposerAudit;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\ComposerAudit\ComposerAuditService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ComposerAuditService::class)]
class ComposerAuditServiceTest extends TestCase
{
    /**
     * @var list<array{\ReflectionProperty, mixed}>
     */
    private array $composerState = [];

    protected function setUp(): void
    {
        InstalledVersions::getAllRawData();
        foreach ([
            [InstalledVersions::class, 'installed'],
            [InstalledVersions::class, 'installedByVendor'],
            [ClassLoader::class, 'registeredLoaders'],
        ] as [$class, $name]) {
            $property = new \ReflectionProperty($class, $name);
            $this->composerState[] = [$property, $property->getValue()];
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->composerState as [$property, $value]) {
            $property->setValue(null, $value);
        }
    }

    #[DataProvider('sourceProvider')]
    public function testReportsSourcesOfAffectedVersions(bool $pluginFirst, string $projectVersion): void
    {
        $project = $this->dataset('shopware/production', __DIR__ . '/./', $projectVersion);
        $plugin = $this->dataset('shopsy/shopsyklaviyo6', __DIR__ . '/custom/plugins/Klaviyo', '7.10.0');
        $this->setDatasets($pluginFirst ? [$plugin, $project, $plugin] : [$project, $plugin, $project]);

        $result = $this->audit();

        static::assertSame(1, $result['packages']);
        static::assertSame(1, $result['vulnerable']);
        static::assertCount(1, $result['advisories']);
        $advisory = $result['advisories'][0];
        static::assertSame('guzzlehttp/guzzle', $advisory['packageName']);
        static::assertSame('7.10.0', $advisory['installedVersion']);
        $expectedSources = $projectVersion === '7.10.0' ? ['project', 'shopsy/shopsyklaviyo6'] : ['shopsy/shopsyklaviyo6'];
        static::assertEqualsCanonicalizing($expectedSources, $advisory['installedSources']);
    }

    public static function sourceProvider(): iterable
    {
        yield 'patched project is registered first' => [false, '7.15.3'];
        yield 'bundled plugin is registered first' => [true, '7.15.3'];
        yield 'same vulnerable version in project and plugin' => [false, '7.10.0'];
        yield 'same vulnerable version with plugin registered first' => [true, '7.10.0'];
    }

    public function testKeepsDifferentVulnerableVersionsSeparate(): void
    {
        $this->setDatasets([
            $this->dataset('shopware/production', __DIR__, '7.9.0'),
            $this->dataset('shopsy/shopsyklaviyo6', __DIR__ . '/custom/plugins/Klaviyo', '7.10.0'),
        ]);

        $result = $this->audit();

        static::assertSame(1, $result['vulnerable']);
        static::assertCount(2, $result['advisories']);
        $byVersion = array_column($result['advisories'], 'installedSources', 'installedVersion');
        static::assertSame(['project'], $byVersion['7.9.0']);
        static::assertSame(['shopsy/shopsyklaviyo6'], $byVersion['7.10.0']);
    }

    public function testUsesInstallPathForUnnamedBundledSource(): void
    {
        $path = __DIR__ . '/custom/plugins/Unnamed';
        $this->setDatasets([$this->dataset('__root__', $path, '7.10.0')]);

        static::assertSame([$path], $this->audit()['advisories'][0]['installedSources']);
    }

    /**
     * @return array<string, mixed>
     */
    private function dataset(string $name, string $path, string $version): array
    {
        return [
            'root' => ['name' => $name, 'install_path' => $path],
            'versions' => [
                'guzzlehttp/guzzle' => ['pretty_version' => $version, 'version' => $version . '.0'],
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $datasets
     */
    private function setDatasets(array $datasets): void
    {
        // Composer has no public API for replacing all datasets. Restore these globals in tearDown.
        $vendors = [];
        $loaders = [];
        foreach ($datasets as $index => $dataset) {
            $vendor = __DIR__ . '/vendor-' . $index;
            $vendors[$vendor] = $dataset;
            $loaders[$vendor] = new ClassLoader($vendor);
        }
        (new \ReflectionProperty(InstalledVersions::class, 'installed'))->setValue(null, []);
        (new \ReflectionProperty(InstalledVersions::class, 'installedByVendor'))->setValue(null, $vendors);
        (new \ReflectionProperty(ClassLoader::class, 'registeredLoaders'))->setValue(null, $loaders);
    }

    /**
     * @return array<string, mixed>
     */
    private function audit(): array
    {
        $client = new MockHttpClient(new MockResponse(json_encode([
            'advisories' => [
                'guzzlehttp/guzzle' => [[
                    'advisoryId' => 'test-advisory',
                    'title' => 'Test vulnerability',
                    'affectedVersions' => '<7.15.0',
                    'severity' => 'high',
                ]],
            ],
        ], \JSON_THROW_ON_ERROR)));
        $service = new ComposerAuditService($client, new ArrayAdapter(), $this->createStub(Connection::class), '6.7.0.0', __DIR__);

        return $service->audit();
    }
}
