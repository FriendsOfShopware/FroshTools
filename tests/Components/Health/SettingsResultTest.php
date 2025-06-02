<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health;

use Frosh\Tools\Components\Health\SettingsResult;
use PHPUnit\Framework\TestCase;

class SettingsResultTest extends TestCase
{
    public function testOkFactory(): void
    {
        $result = SettingsResult::ok('test-id', 'Test snippet', 'current-value', 'recommended-value', 'https://example.com');
        
        $reflection = new \ReflectionClass($result);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $snippetProperty = $reflection->getProperty('snippet');
        $snippetProperty->setAccessible(true);
        
        $this->assertEquals('test-id', $idProperty->getValue($result));
        $this->assertEquals('Test snippet', $snippetProperty->getValue($result));
        $this->assertEquals('current-value', $result->current);
        $this->assertEquals('recommended-value', $result->recommended);
        $this->assertEquals('https://example.com', $result->url);
        $this->assertEquals(SettingsResult::GREEN, $result->state);
    }

    public function testWarningFactory(): void
    {
        $result = SettingsResult::warning('warning-id', 'Warning message', '5', '10');
        
        $reflection = new \ReflectionClass($result);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $snippetProperty = $reflection->getProperty('snippet');
        $snippetProperty->setAccessible(true);
        
        $this->assertEquals('warning-id', $idProperty->getValue($result));
        $this->assertEquals('Warning message', $snippetProperty->getValue($result));
        $this->assertEquals('5', $result->current);
        $this->assertEquals('10', $result->recommended);
        $this->assertNull($result->url);
        $this->assertEquals(SettingsResult::WARNING, $result->state);
    }

    public function testErrorFactory(): void
    {
        $result = SettingsResult::error('error-id', 'Error message', 'bad', 'good', 'https://docs.example.com');
        
        $reflection = new \ReflectionClass($result);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $snippetProperty = $reflection->getProperty('snippet');
        $snippetProperty->setAccessible(true);
        
        $this->assertEquals('error-id', $idProperty->getValue($result));
        $this->assertEquals('Error message', $snippetProperty->getValue($result));
        $this->assertEquals('bad', $result->current);
        $this->assertEquals('good', $result->recommended);
        $this->assertEquals('https://docs.example.com', $result->url);
        $this->assertEquals(SettingsResult::ERROR, $result->state);
    }

    public function testInfoFactory(): void
    {
        $result = SettingsResult::info('info-id', 'Info message', 'info-value');
        
        $reflection = new \ReflectionClass($result);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $snippetProperty = $reflection->getProperty('snippet');
        $snippetProperty->setAccessible(true);
        
        $this->assertEquals('info-id', $idProperty->getValue($result));
        $this->assertEquals('Info message', $snippetProperty->getValue($result));
        $this->assertEquals('info-value', $result->current);
        $this->assertEquals('', $result->recommended);
        $this->assertNull($result->url);
        $this->assertEquals(SettingsResult::INFO, $result->state);
    }

    public function testDefaultValues(): void
    {
        $result = SettingsResult::ok('minimal', 'Minimal result');
        
        $reflection = new \ReflectionClass($result);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $snippetProperty = $reflection->getProperty('snippet');
        $snippetProperty->setAccessible(true);
        
        $this->assertEquals('minimal', $idProperty->getValue($result));
        $this->assertEquals('Minimal result', $snippetProperty->getValue($result));
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