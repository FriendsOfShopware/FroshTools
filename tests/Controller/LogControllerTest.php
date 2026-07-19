<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Controller\LogController;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(LogController::class)]
class LogControllerTest extends IntegrationTestCase
{
    private LogController $controller;

    protected function setUp(): void
    {
        $this->controller = static::getContainer()->get(LogController::class);
    }

    public function testGetLogFilesReturnsListOfLogFiles(): void
    {
        $response = $this->controller->getLogFiles();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $files = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($files);

        foreach ($files as $file) {
            static::assertIsArray($file);
            static::assertArrayHasKey('name', $file);
            static::assertIsString($file['name']);
        }
    }

    public function testGetLogReturnsEntriesWithFileSizeHeader(): void
    {
        $files = json_decode((string) $this->controller->getLogFiles()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        if ($files === []) {
            static::markTestSkipped('No log files present in the kernel logs dir');
        }

        $request = new Request(['file' => $files[0]['name'], 'offset' => 0]);
        $response = $this->controller->getLog($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertTrue($response->headers->has('file-size'));

        $entries = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($entries);

        foreach ($entries as $entry) {
            static::assertArrayHasKey('message', $entry);
            static::assertArrayHasKey('channel', $entry);
            static::assertArrayHasKey('date', $entry);
            static::assertArrayHasKey('level', $entry);
        }
    }
}
