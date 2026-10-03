<?php

declare(strict_types=1);

namespace Frosh\Tools\Backport;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginZipDetector;
use Shopware\Core\Framework\Plugin\Util\ZipUtils;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * @internal
 */
final class ExtensionUploadSubscriber implements EventSubscriberInterface
{
    private const UPLOAD_CONTEXT_ATTRIBUTE = '_frosh_tools_system_activity_upload';

    /**
     * composer.json and manifest.xml are metadata, not archives. Reject anything larger
     * before ZipArchive materializes the decompressed entry.
     */
    private const MAX_METADATA_BYTES = 1024 * 1024;

    public function __construct(
        private readonly PluginZipDetector $pluginZipDetector,
        private readonly SystemActivitySubscriber $activitySubscriber,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'api.extension.upload.request' => 'onUploadRequest',
            'api.extension.upload.response' => 'onUploadResponse',
        ];
    }

    public function onUploadRequest(RequestEvent $event): void
    {
        $file = $event->getRequest()->files->get('file');
        if (!$file instanceof UploadedFile || $file->getPathname() === '') {
            return;
        }

        try {
            $type = $this->pluginZipDetector->detect($file->getPathname());
            $metadata = $this->readUploadMetadata($file->getPathname(), $type);
        } catch (\Throwable) {
            // Keep invalid upload handling with Shopware's controller.
            return;
        }

        $event->getRequest()->attributes->set(self::UPLOAD_CONTEXT_ATTRIBUTE, [
            'type' => $type,
            'filename' => $file->getClientOriginalName(),
            ...$metadata,
        ]);
    }

    public function onUploadResponse(ResponseEvent $event): void
    {
        if (!$event->getResponse()->isSuccessful()) {
            return;
        }

        $request = $event->getRequest();
        $upload = $request->attributes->get(self::UPLOAD_CONTEXT_ATTRIBUTE);
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!\is_array($upload) || !$context instanceof Context) {
            return;
        }

        $type = $upload['type'] ?? null;
        if (!\is_string($type) || !\in_array($type, [PluginManagementService::PLUGIN, PluginManagementService::APP], true)) {
            return;
        }

        $this->logger->info($type . ':upload', $this->activitySubscriber->withoutNullValues([
            'filename' => $upload['filename'] ?? null,
            $type . 'Name' => $upload['name'] ?? null,
            $type . 'Version' => $upload['version'] ?? null,
            ...$this->activitySubscriber->actor($context),
        ]));
    }

    /**
     * @return array{name: string, version: string|null}
     */
    private function readUploadMetadata(string $path, string $type): array
    {
        $archive = ZipUtils::openZip($path);
        try {
            $entry = $archive->statIndex(0);
            \assert($entry !== false);
            $directory = explode('/', $entry['name'])[0];
            if ($type === PluginManagementService::APP) {
                $manifestXml = $this->readBoundedZipEntry($archive, $directory . '/manifest.xml');
                \assert(\is_string($manifestXml));
                $metadata = Manifest::createFromXml($manifestXml)->getMetadata();

                return ['name' => $metadata->getName(), 'version' => $metadata->getVersion()];
            }

            $composerJson = $this->readBoundedZipEntry($archive, $directory . '/composer.json');
            $composer = \is_string($composerJson) ? json_decode($composerJson, true, flags: \JSON_THROW_ON_ERROR) : null;
            $extra = \is_array($composer) ? ($composer['extra'] ?? null) : null;
            $class = \is_array($extra) ? ($extra['shopware-plugin-class'] ?? null) : null;
            $version = \is_array($composer) ? ($composer['version'] ?? null) : null;

            return [
                'name' => \is_string($class) && $class !== '' ? basename(str_replace('\\', '/', $class)) : $directory,
                'version' => \is_string($version) ? $version : null,
            ];
        } finally {
            $archive->close();
        }
    }

    private function readBoundedZipEntry(\ZipArchive $archive, string $name): ?string
    {
        $stat = $archive->statName($name);
        if ($stat === false) {
            return null;
        }

        $size = $stat['size'];
        if ($size < 0 || $size > self::MAX_METADATA_BYTES) {
            throw new \RuntimeException(\sprintf('Extension metadata "%s" exceeds the allowed size.', $name));
        }

        if ($size === 0) {
            return '';
        }

        // Length 0 means "read the whole entry", so only pass a positive checked size.
        $contents = $archive->getFromName($name, $size);

        return \is_string($contents) ? $contents : null;
    }
}
