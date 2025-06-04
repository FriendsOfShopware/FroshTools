<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\unit\Components\Health;

use Frosh\Tools\Components\Health\SettingsResult;
use PHPUnit\Framework\TestCase;

class SettingsResultTest extends TestCase
{
    public function testOkFactory(): void
    {
        $result = SettingsResult::ok('test-id', 'Test snippet', 'current-value', 'recommended-value', 'https://example.com');

        $this->assertEquals('test-id', $result->getId());
        $this->assertEquals('Test snippet', $result->snippet);
        $this->assertEquals('current-value', $result->current);
        $this->assertEquals('recommended-value', $result->recommended);
        $this->assertEquals('https://example.com', $result->url);
        $this->assertEquals(SettingsResult::GREEN, $result->state);
    }

    public function testWarningFactory(): void
    {
        $result = SettingsResult::warning('warning-id', 'Warning message', '5', '10');

        $this->assertEquals('warning-id', $result->getId());
        $this->assertEquals('Warning message', $result->snippet);
        $this->assertEquals('5', $result->current);
        $this->assertEquals('10', $result->recommended);
        $this->assertNull($result->url);
        $this->assertEquals(SettingsResult::WARNING, $result->state);
    }

    public function testErrorFactory(): void
    {
        $result = SettingsResult::error('error-id', 'Error message', 'bad', 'good', 'https://docs.example.com');

        $this->assertEquals('error-id', $result->getId());
        $this->assertEquals('Error message', $result->snippet);
        $this->assertEquals('bad', $result->current);
        $this->assertEquals('good', $result->recommended);
        $this->assertEquals('https://docs.example.com', $result->url);
        $this->assertEquals(SettingsResult::ERROR, $result->state);
    }

    public function testInfoFactory(): void
    {
        $result = SettingsResult::info('info-id', 'Info message', 'info-value');

        $this->assertEquals('info-id', $result->getId());
        $this->assertEquals('Info message', $result->snippet);
        $this->assertEquals('info-value', $result->current);
        $this->assertEquals('', $result->recommended);
        $this->assertNull($result->url);
        $this->assertEquals(SettingsResult::INFO, $result->state);
    }

    public function testDefaultValues(): void
    {
        $result = SettingsResult::ok('minimal', 'Minimal result');

        $this->assertEquals('minimal', $result->getId());
        $this->assertEquals('Minimal result', $result->snippet);
        $this->assertEquals('', $result->current);
        $this->assertEquals('', $result->recommended);
        $this->assertNull($result->url);
        $this->assertEquals(SettingsResult::GREEN, $result->state);
    }

    public function testStateConstants(): void
    {
        $this->assertEquals('STATE_OK', SettingsResult::GREEN);
        $this->assertEquals('STATE_WARNING', SettingsResult::WARNING);
        $this->assertEquals('STATE_ERROR', SettingsResult::ERROR);
        $this->assertEquals('STATE_INFO', SettingsResult::INFO);
    }

    public function testToArray(): void
    {
        $result = SettingsResult::error('test', 'Test message', 'current', 'recommended', 'https://example.com');

        $array = $result->jsonSerialize();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('snippet', $array);
        $this->assertArrayHasKey('current', $array);
        $this->assertArrayHasKey('recommended', $array);
        $this->assertArrayHasKey('state', $array);
        $this->assertArrayHasKey('url', $array);

        $this->assertEquals('test', $array['id']);
        $this->assertEquals('Test message', $array['snippet']);
        $this->assertEquals('current', $array['current']);
        $this->assertEquals('recommended', $array['recommended']);
        $this->assertEquals(SettingsResult::ERROR, $array['state']);
        $this->assertEquals('https://example.com', $array['url']);
    }
}
