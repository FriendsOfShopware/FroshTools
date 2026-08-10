<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Unit\Log;

use Frosh\Tools\Components\Log\PregMonologParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PregMonologParser::class)]
class PregMonologParserTest extends TestCase
{
    private PregMonologParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PregMonologParser();
    }

    public function testParsesStandardLine(): void
    {
        $line = '[2024-03-15T10:30:00.123456+00:00] request.ERROR: Uncaught PHP Exception [] []';
        $entry = $this->parser->parse($line);

        static::assertSame('2024-03-15T10:30:00.123456+00:00', $entry['date']);
        static::assertSame('request', $entry['channel']);
        static::assertSame('ERROR', $entry['level']);
        static::assertSame(' Uncaught PHP Exception [] []', $entry['message']);
    }

    public function testDottedChannel(): void
    {
        $line = '[2024-03-15T10:29:59.000000+00:00] doctrine.dbal.INFO: Connecting [] []';
        $entry = $this->parser->parse($line);

        static::assertSame('doctrine.dbal', $entry['channel']);
        static::assertSame('INFO', $entry['level']);
    }

    public function testUnmatchedFallsBackToUnknown(): void
    {
        $entry = $this->parser->parse('not a log line');

        static::assertSame('unknown', $entry['channel']);
        static::assertSame('unknown', $entry['level']);
        static::assertSame('unknown', $entry['date']);
        static::assertSame('not a log line', $entry['message']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function levelsProvider(): iterable
    {
        foreach (['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'] as $level) {
            yield $level => [$level];
        }
    }

    #[DataProvider('levelsProvider')]
    public function testAllLevels(string $level): void
    {
        $line = \sprintf('[2024-01-01T00:00:00+00:00] ch.%s: msg', $level);
        $entry = $this->parser->parse($line);
        static::assertSame($level, $entry['level']);
    }
}
