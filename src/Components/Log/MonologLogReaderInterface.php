<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Log;

/**
 * Reads a Monolog log file and returns parsed entries (newest first).
 *
 * @phpstan-type MonologEntry array{date: string, channel: string, level: string, message: string}
 * @phpstan-type MonologPage array{entries: list<MonologEntry>, total: int}
 */
interface MonologLogReaderInterface
{
    /**
     * Read a page of log lines from the end of the file.
     *
     * offset 0 = newest line. Lines are already parsed.
     *
     * @return MonologPage
     */
    public function readBackwards(string $filePath, int $offset, int $limit): array;

    /**
     * Stream parsed entries newest-first (PHP generator / "yield").
     *
     * @return \Generator<int, MonologEntry>
     */
    public function yieldBackwards(string $filePath): \Generator;

    /**
     * Implementation id: "ffi" or "preg".
     */
    public function backend(): string;
}
