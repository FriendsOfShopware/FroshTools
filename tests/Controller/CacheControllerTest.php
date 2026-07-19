<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Controller\CacheController;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(CacheController::class)]
class CacheControllerTest extends IntegrationTestCase
{
    private CacheController $controller;

    protected function setUp(): void
    {
        $this->controller = static::getContainer()->get(CacheController::class);
    }

    public function testCacheStatisticsReturnsListWithKnownAdapters(): void
    {
        $response = $this->controller->cacheStatistics();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $entries = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($entries);
        static::assertNotEmpty($entries);

        $names = array_column($entries, 'name');
        static::assertContains('cache.app', $names);

        foreach ($entries as $entry) {
            static::assertIsArray($entry);
            static::assertArrayHasKey('name', $entry);
            static::assertArrayHasKey('active', $entry);
            static::assertArrayHasKey('size', $entry);
            static::assertArrayHasKey('freeSpace', $entry);
            static::assertArrayHasKey('type', $entry);
        }
    }

    public function testClearCacheWithUnknownFolderReturnsNoContent(): void
    {
        $folder = 'definitely-not-existing-frosh';
        $cacheDir = (string) static::getContainer()->getParameter('kernel.cache_dir');
        $folderPath = \dirname($cacheDir) . '/' . $folder;

        try {
            $response = $this->controller->clearCache($folder);

            // Surprising, but intended by the implementation: the controller does not
            // check whether the folder is registered or exists on disk, it simply calls
            // CacheHelper::removeDir() and always answers with 204.
            static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        } finally {
            // CacheHelper::removeDir() creates the missing directory as a side effect
            // (rsync syncs an empty directory into it), so clean it up again.
            if (is_dir($folderPath) && (scandir($folderPath) ?: []) === ['.', '..']) {
                rmdir($folderPath);
            }
        }
    }
}
