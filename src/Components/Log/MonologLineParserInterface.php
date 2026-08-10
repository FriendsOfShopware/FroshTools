<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Log;

/**
 * Parses a single Monolog line into date / channel / level / message.
 *
 * @phpstan-type MonologEntry array{date: string, channel: string, level: string, message: string}
 */
interface MonologLineParserInterface
{
    /**
     * @return MonologEntry
     */
    public function parse(string $line): array;

    /**
     * Implementation id for diagnostics: "ffi" or "preg".
     */
    public function backend(): string;
}
