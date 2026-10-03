<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Backport;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Backport\ExtensionUploadSubscriber;
use Frosh\Tools\Backport\SystemActivitySubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginZipDetector;
use Shopware\Core\PlatformRequest;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ExtensionUploadSubscriber::class)]
final class ExtensionUploadSubscriberTest extends TestCase
{
    private string $zipPath;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'frosh-tools-upload-test');
        static::assertIsString($path);
        $this->zipPath = $path;

        $archive = new \ZipArchive();
        static::assertTrue($archive->open($this->zipPath, \ZipArchive::OVERWRITE));
        $archive->addFromString('ExamplePlugin/composer.json', json_encode([
            'version' => '2.0.0',
            'extra' => ['shopware-plugin-class' => 'Example\\ExamplePlugin'],
        ], \JSON_THROW_ON_ERROR));
        $archive->close();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->zipPath);
    }

    public function testLogsPluginMetadataAfterSuccessfulUpload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('plugin:upload', [
            'filename' => 'plugin.zip',
            'pluginName' => 'ExamplePlugin',
            'pluginVersion' => '2.0.0',
            'actorType' => 'system',
        ]);
        $subscriber = new ExtensionUploadSubscriber(
            new PluginZipDetector(),
            new SystemActivitySubscriber($logger, static::createStub(Connection::class)),
            $logger,
        );
        $request = new Request([], [], [
            PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT => new Context(new SystemSource()),
        ], [], [
            'file' => new UploadedFile($this->zipPath, 'plugin.zip', 'application/zip', null, true),
        ]);
        $kernel = static::createStub(HttpKernelInterface::class);

        $subscriber->onUploadRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $subscriber->onUploadResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Response(status: Response::HTTP_NO_CONTENT)));
    }

    public function testDoesNotLogFailedUpload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        $subscriber = new ExtensionUploadSubscriber(
            new PluginZipDetector(),
            new SystemActivitySubscriber($logger, static::createStub(Connection::class)),
            $logger,
        );
        $request = new Request([], [], [
            PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT => Context::createDefaultContext(),
        ], [], [
            'file' => new UploadedFile($this->zipPath, 'plugin.zip', 'application/zip', null, true),
        ]);
        $kernel = static::createStub(HttpKernelInterface::class);

        $subscriber->onUploadRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $subscriber->onUploadResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Response(status: Response::HTTP_BAD_REQUEST)));
    }

    public function testDoesNotReadOversizedPluginMetadata(): void
    {
        $archive = new \ZipArchive();
        static::assertTrue($archive->open($this->zipPath, \ZipArchive::OVERWRITE));
        $archive->addFromString('ExamplePlugin/composer.json', str_repeat('a', (1024 * 1024) + 1));
        $archive->close();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        $subscriber = new ExtensionUploadSubscriber(
            new PluginZipDetector(),
            new SystemActivitySubscriber($logger, static::createStub(Connection::class)),
            $logger,
        );
        $request = new Request([], [], [
            PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT => Context::createDefaultContext(),
        ], [], [
            'file' => new UploadedFile($this->zipPath, 'plugin.zip', 'application/zip', null, true),
        ]);
        $kernel = static::createStub(HttpKernelInterface::class);

        $subscriber->onUploadRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $subscriber->onUploadResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Response(status: Response::HTTP_NO_CONTENT)));
    }
}
