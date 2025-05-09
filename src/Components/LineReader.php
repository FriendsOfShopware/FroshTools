<?php

declare(strict_types=1);

namespace Frosh\Tools\Components;

/**
 * Taken from https://github.com/bcremer/LineReader/blob/main/src/LineReader.php
 * Thanks bcremer for building it. It's easier to copy one class then require a dependency in Shopware
 */
final class LineReader
{
    /**
     * Prevent instantiation
     */
    private function __construct()
    {
    }

    /**
     * @throws \InvalidArgumentException if $filePath is not readable
     *
     * @return \Generator<int, string>
     */
    public static function readLines(string $filePath): \Generator
    {
        if (!$fh = @fopen($filePath, 'r')) {
            throw new \InvalidArgumentException('Cannot open file for reading: ' . $filePath);
        }

        return self::read($fh);
    }

    /**
     * @throws \InvalidArgumentException if $filePath is not readable
     *
     * @return \Generator<int, string>
     */
    public static function readLinesBackwards(string $filePath): \Generator
    {
        if (!$fh = @fopen($filePath, 'r')) {
            throw new \InvalidArgumentException('Cannot open file for reading: ' . $filePath);
        }

        $size = filesize($filePath);
        if (!\is_int($size)) {
            throw new \RuntimeException('Could not get file size');
        }

        return self::readBackwards($fh, $size);
    }

    /**
     * @param resource $fh
     */
    private static function read($fh): \Generator
    {
        while (false !== $line = fgets($fh)) {
            yield rtrim($line, "\n");
        }

        fclose($fh);
    }

    /**
     * Read a file from the end using a buffer.
     *
     * This is way more efficient than using the naive method
     * of reading the file backwards byte by byte looking for
     * a newline character.
     *
     * @see http://stackoverflow.com/a/10494801/147634
     *
     * @param resource $fh
     *
     * @return \Generator<int, string>
     */
    private static function readBackwards($fh, int $pos): \Generator
    {
        $buffer = null;
        $bufferSize = 4096;

        if ($pos === 0) {
            return;
        }

        while (true) {
            if (isset($buffer[1])) { // faster than count($buffer) > 1
                yield array_pop($buffer);
                continue;
            }

            if ($pos === 0 && \is_array($buffer)) {
                yield array_pop($buffer);
                break;
            }

            if ($bufferSize > $pos) {
                $bufferSize = $pos;
                $pos = 0;
            } else {
                $pos -= $bufferSize;
            }
            fseek($fh, $pos);
            if ($bufferSize < 0) {
                throw new \RuntimeException('Buffer size cannot be negative');
            }
            // @phpstan-ignore-next-line
            $chunk = fread($fh, $bufferSize);
            if (!\is_string($chunk)) {
                throw new \RuntimeException('Could not read file');
            }
            if ($buffer === null) {
                // remove single trailing newline, rtrim cannot be used here
                if (str_ends_with($chunk, "\n")) {
                    $chunk = substr($chunk, 0, -1);
                }
                $buffer = explode("\n", $chunk);
            } else {
                $buffer = explode("\n", $chunk . $buffer[0]);
            }
        }
    }
}
