<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Controller\ShopwareFilesController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ShopwareFilesController::class)]
class ShopwareFilesControllerTest extends TestCase
{
    private string $temporaryDirectory;

    private string $projectDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/frosh-tools-' . bin2hex(random_bytes(8));
        $this->projectDirectory = $this->temporaryDirectory . '/shop';

        mkdir($this->projectDirectory . '/vendor/shopware/core/Test', 0777, true);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->temporaryDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($this->temporaryDirectory);
    }

    public function testReturnsContentsForFileFromChecksumManifest(): void
    {
        $file = 'vendor/shopware/core/Test/example.php';
        file_put_contents($this->projectDirectory . '/' . $file, 'modified content');

        $controller = $this->createController(new MockHttpClient([
            new MockResponse('checksum  ' . $file),
            new MockResponse('original content'),
        ]));

        $response = $controller->getFileContents(new Request(['file' => $file]));
        $data = json_decode($response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        static::assertSame('modified content', $data['content']);
        static::assertSame('original content', $data['originalContent']);
    }

    #[DataProvider('environmentFiles')]
    public function testRejectsEnvironmentFilesWithoutRequestingChecksumManifest(string $file): void
    {
        $controller = $this->createController(new MockHttpClient(
            static function (): never {
                static::fail('The checksum manifest must not be requested for environment files');
            },
        ));
        $response = $controller->getFileContents(new Request(['file' => $file]));

        static::assertSame(['error' => 'File is invalid'], json_decode($response->getContent(), true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testRejectsManifestPathOutsideProjectWithMatchingPrefix(): void
    {
        $siblingDirectory = $this->temporaryDirectory . '/shop-backup';
        mkdir($siblingDirectory);
        file_put_contents($siblingDirectory . '/secret.txt', 'secret');

        $file = '../shop-backup/secret.txt';
        $controller = $this->createController(new MockHttpClient(new MockResponse('checksum  ' . $file)));
        $response = $controller->getFileContents(new Request(['file' => $file]));

        static::assertSame(['error' => 'File is invalid'], json_decode($response->getContent(), true, flags: \JSON_THROW_ON_ERROR));
    }

    #[DataProvider('environmentFiles')]
    public function testRestoreRejectsEnvironmentFiles(string $file): void
    {
        $path = $this->projectDirectory . '/' . str_replace('\\', '/', $file);
        file_put_contents($path, 'DATABASE_URL=secret');

        $controller = $this->createController(new MockHttpClient(
            static function (): never {
                static::fail('The checksum manifest must not be requested for environment files');
            },
        ));
        $response = $controller->restoreShopwareFile(new Request(['file' => $file]), Context::createDefaultContext());

        static::assertSame(['error' => 'File is invalid'], json_decode($response->getContent(), true, flags: \JSON_THROW_ON_ERROR));
        static::assertSame('DATABASE_URL=secret', file_get_contents($path));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function environmentFiles(): iterable
    {
        yield 'base' => ['.env'];
        yield 'local' => ['.env.local'];
        yield 'production' => ['.env.prod'];
        yield 'production local' => ['.env.prod.local'];
        yield 'compiled' => ['.env.local.php'];
        yield 'nested' => ['vendor/shopware/core/Test/.env'];
        yield 'nested Windows path' => ['vendor\\shopware\\core\\Test\\.env.local'];
    }

    private function createController(MockHttpClient $httpClient): ShopwareFilesController
    {
        return new ShopwareFilesController(
            '6.7.0.0',
            $this->projectDirectory,
            [],
            $this->createStub(LoggerInterface::class),
            $this->createStub(EntityRepository::class),
            $this->createStub(EntityRepository::class),
            $httpClient,
        );
    }
}
