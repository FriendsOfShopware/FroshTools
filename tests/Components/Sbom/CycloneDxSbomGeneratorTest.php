<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Sbom;

use Frosh\Tools\Components\Exception\FroshToolsException;
use Frosh\Tools\Components\Sbom\CycloneDxSbomGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CycloneDxSbomGenerator::class)]
class CycloneDxSbomGeneratorTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/frosh-tools-sbom-' . uniqid('', true);
        mkdir($this->projectDir);

        file_put_contents($this->projectDir . '/composer.json', json_encode([
            'name' => 'acme/shop',
            'version' => '1.0.0',
            'require' => [
                'php' => '>=8.1',
                'symfony/console' => '^6.3',
            ],
            'require-dev' => [
                'phpunit/phpunit' => '^10.0',
            ],
        ], \JSON_THROW_ON_ERROR));

        file_put_contents($this->projectDir . '/composer.lock', json_encode([
            'packages' => [
                [
                    'name' => 'symfony/console',
                    'version' => 'v6.3.0',
                    'type' => 'library',
                    'description' => 'Eases the creation of beautiful and testable command line interfaces',
                    'homepage' => 'https://symfony.com',
                    'license' => ['MIT'],
                    'require' => [
                        'php' => '>=8.1',
                        'symfony/string' => '^6.3',
                    ],
                    'dist' => [
                        'type' => 'zip',
                        'url' => 'https://api.github.com/repos/symfony/console/zipball/abc',
                        'shasum' => 'abcdef0123456789',
                    ],
                    'source' => [
                        'type' => 'git',
                        'url' => 'https://github.com/symfony/console.git',
                    ],
                ],
                [
                    'name' => 'symfony/string',
                    'version' => 'v6.3.0',
                    'type' => 'library',
                    'license' => ['MIT'],
                    'require' => [
                        'php' => '>=8.1',
                    ],
                ],
            ],
            'packages-dev' => [
                [
                    'name' => 'phpunit/phpunit',
                    'version' => '10.0.0',
                    'license' => ['BSD-3-Clause'],
                ],
            ],
        ], \JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->projectDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->projectDir);
    }

    public function testGenerateExcludesDevDependenciesByDefault(): void
    {
        $bom = $this->generator()->generate();

        static::assertSame('CycloneDX', $bom['bomFormat']);
        static::assertSame('1.7', $bom['specVersion']);
        static::assertStringStartsWith('urn:uuid:', $bom['serialNumber']);
        static::assertSame(1, $bom['version']);

        static::assertSame('application', $bom['metadata']['component']['type']);
        static::assertSame('acme/shop', $bom['metadata']['component']['name']);
        static::assertSame('1.0.0', $bom['metadata']['component']['version']);
        static::assertSame('app:acme/shop@1.0.0', $bom['metadata']['component']['bom-ref']);

        static::assertSame('FroshTools', $bom['metadata']['tools']['components'][0]['name']);
        static::assertSame('frosh', $bom['metadata']['tools']['components'][0]['group']);

        static::assertCount(2, $bom['components']);

        $console = $this->findComponent($bom, 'console');
        static::assertSame('library', $console['type']);
        static::assertSame('symfony', $console['group']);
        static::assertSame('v6.3.0', $console['version']);
        static::assertSame('pkg:composer/symfony/console@v6.3.0', $console['purl']);
        static::assertSame('pkg:composer/symfony/console@v6.3.0', $console['bom-ref']);
        static::assertSame('MIT', $console['licenses'][0]['license']['id']);
        static::assertSame('SHA-1', $console['hashes'][0]['alg']);
        static::assertSame('abcdef0123456789', $console['hashes'][0]['content']);

        $refTypes = array_column($console['externalReferences'], 'type');
        sort($refTypes);
        static::assertSame(['distribution', 'vcs', 'website'], $refTypes);
    }

    public function testGenerateIncludesDevDependenciesWhenRequested(): void
    {
        $bom = $this->generator()->generate(true);

        static::assertCount(3, $bom['components']);
        static::assertNotNull($this->findComponent($bom, 'phpunit'));

        $rootDeps = $this->findDependency($bom, 'app:acme/shop@1.0.0');
        static::assertSame([
            'pkg:composer/phpunit/phpunit@10.0.0',
            'pkg:composer/symfony/console@v6.3.0',
        ], $rootDeps['dependsOn']);
    }

    public function testRootDependsOnlyOnDirectRequirements(): void
    {
        $bom = $this->generator()->generate();

        $rootDeps = $this->findDependency($bom, 'app:acme/shop@1.0.0');
        // transitive symfony/string must not appear as a direct root dependency
        static::assertSame(['pkg:composer/symfony/console@v6.3.0'], $rootDeps['dependsOn']);
    }

    public function testDependenciesSkipPlatformRequirements(): void
    {
        $bom = $this->generator()->generate();

        $consoleDeps = $this->findDependency($bom, 'pkg:composer/symfony/console@v6.3.0');
        static::assertSame(['pkg:composer/symfony/string@v6.3.0'], $consoleDeps['dependsOn']);
    }

    public function testProvideAndReplaceResolveDependencyEdges(): void
    {
        file_put_contents($this->projectDir . '/composer.json', json_encode([
            'name' => 'acme/shop',
            'version' => '1.0.0',
            'require' => [
                'psr/log' => '^3.0',
            ],
        ], \JSON_THROW_ON_ERROR));

        file_put_contents($this->projectDir . '/composer.lock', json_encode([
            'packages' => [
                [
                    'name' => 'monolog/monolog',
                    'version' => '3.0.0',
                    'license' => ['MIT'],
                    'provide' => [
                        'psr/log-implementation' => '3.0.0',
                    ],
                    'replace' => [
                        'psr/log' => '3.0.0',
                    ],
                    'require' => [],
                ],
                [
                    'name' => 'acme/logger-user',
                    'version' => '1.0.0',
                    'license' => ['MIT'],
                    'require' => [
                        'psr/log-implementation' => '^3.0',
                    ],
                ],
            ],
            'packages-dev' => [],
        ], \JSON_THROW_ON_ERROR));

        $bom = $this->generator()->generate();
        $monologRef = 'pkg:composer/monolog/monolog@3.0.0';

        $rootDeps = $this->findDependency($bom, 'app:acme/shop@1.0.0');
        static::assertSame([$monologRef], $rootDeps['dependsOn']);

        $userDeps = $this->findDependency($bom, 'pkg:composer/acme/logger-user@1.0.0');
        static::assertSame([$monologRef], $userDeps['dependsOn']);
    }

    public function testPurlEncodesReservedCharacters(): void
    {
        file_put_contents($this->projectDir . '/composer.json', json_encode([
            'name' => 'acme/shop',
            'version' => '1.0.0',
            'require' => [
                'acme/dev-package' => 'dev-feature/foo',
            ],
        ], \JSON_THROW_ON_ERROR));

        file_put_contents($this->projectDir . '/composer.lock', json_encode([
            'packages' => [
                [
                    'name' => 'acme/dev-package',
                    'version' => 'dev-feature/foo',
                    'license' => ['MIT'],
                ],
                [
                    'name' => 'acme/build-meta',
                    'version' => '1.0.0+build.1',
                    'license' => ['MIT'],
                ],
            ],
            'packages-dev' => [],
        ], \JSON_THROW_ON_ERROR));

        $bom = $this->generator()->generate();

        $dev = $this->findComponent($bom, 'dev-package');
        static::assertSame('pkg:composer/acme/dev-package@dev-feature%2Ffoo', $dev['purl']);
        static::assertSame('pkg:composer/acme/dev-package@dev-feature%2Ffoo', $dev['bom-ref']);

        $build = $this->findComponent($bom, 'build-meta');
        static::assertSame('pkg:composer/acme/build-meta@1.0.0%2Bbuild.1', $build['purl']);
    }

    public function testFreeTextLicenseUsesName(): void
    {
        file_put_contents($this->projectDir . '/composer.lock', json_encode([
            'packages' => [
                [
                    'name' => 'acme/private',
                    'version' => '1.0.0',
                    'license' => ['proprietary'],
                ],
            ],
            'packages-dev' => [],
        ], \JSON_THROW_ON_ERROR));

        $bom = $this->generator()->generate();
        $component = $this->findComponent($bom, 'private');

        static::assertSame('proprietary', $component['licenses'][0]['license']['name']);
        static::assertArrayNotHasKey('id', $component['licenses'][0]['license']);
    }

    public function testGenerateJsonIsValidCycloneDx(): void
    {
        $json = $this->generator()->generateJson();
        $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('CycloneDX', $decoded['bomFormat']);
        static::assertSame('1.7', $decoded['specVersion']);
        static::assertSame('FroshTools', $decoded['metadata']['tools']['components'][0]['name']);
    }

    public function testMissingLockThrows(): void
    {
        unlink($this->projectDir . '/composer.lock');

        $this->expectException(FroshToolsException::class);
        $this->generator()->generate();
    }

    public function testProjectTypeMapsToApplication(): void
    {
        file_put_contents($this->projectDir . '/composer.lock', json_encode([
            'packages' => [
                [
                    'name' => 'shopware/production',
                    'version' => '6.6.0',
                    'type' => 'project',
                    'license' => ['MIT'],
                ],
            ],
            'packages-dev' => [],
        ], \JSON_THROW_ON_ERROR));

        $bom = $this->generator()->generate();
        $component = $this->findComponent($bom, 'production');

        static::assertSame('application', $component['type']);
    }

    private function generator(): CycloneDxSbomGenerator
    {
        return new CycloneDxSbomGenerator($this->projectDir, '6.6.0.0');
    }

    /**
     * @param array<string, mixed> $bom
     *
     * @return array<string, mixed>
     */
    private function findComponent(array $bom, string $name): array
    {
        foreach ($bom['components'] as $component) {
            if (($component['name'] ?? null) === $name) {
                return $component;
            }
        }

        static::fail(\sprintf('Component "%s" not found', $name));
    }

    /**
     * @param array<string, mixed> $bom
     *
     * @return array<string, mixed>
     */
    private function findDependency(array $bom, string $ref): array
    {
        foreach ($bom['dependencies'] as $dependency) {
            if (($dependency['ref'] ?? null) === $ref) {
                return $dependency;
            }
        }

        static::fail(\sprintf('Dependency "%s" not found', $ref));
    }
}
