<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Log;

use Frosh\Tools\Components\LineReader;

/**
 * Fallback reader: PHP LineReader + PCRE parse (no FFI / no .so).
 */
final class PregMonologLogReader implements MonologLogReaderInterface
{
    public function __construct(
        private readonly PregMonologParser $parser = new PregMonologParser(),
    ) {
    }

    public function readBackwards(string $filePath, int $offset, int $limit): array
    {
        $file = new \SplFileObject($filePath, 'r');
        $file->seek(\PHP_INT_MAX);
        $total = $file->key();

        $entries = [];
        $reader = new \LimitIterator(LineReader::readLinesBackwards($filePath), $offset, $limit);
        /** @var string $line */
        foreach ($reader as $line) {
            $entries[] = $this->parser->parse($line);
        }

        return [
            'entries' => $entries,
            'total' => $total,
        ];
    }

    public function yieldBackwards(string $filePath): \Generator
    {
        foreach (LineReader::readLinesBackwards($filePath) as $line) {
            yield $this->parser->parse($line);
        }
    }

    public function backend(): string
    {
        return 'preg';
    }
}
