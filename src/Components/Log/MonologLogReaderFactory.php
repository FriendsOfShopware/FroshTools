<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Log;

/**
 * Prefers native file reader (open + parse in C via FFI); falls back to PHP.
 */
final class MonologLogReaderFactory
{
    private static ?MonologLogReaderInterface $cached = null;

    public function __invoke(): MonologLogReaderInterface
    {
        return self::create();
    }

    public static function create(bool $forcePreg = false): MonologLogReaderInterface
    {
        if ($forcePreg) {
            return new PregMonologLogReader();
        }

        if (self::$cached !== null) {
            return self::$cached;
        }

        $ffi = FfiMonologLogReader::tryCreate();
        self::$cached = $ffi ?? new PregMonologLogReader();

        return self::$cached;
    }

    /**
     * @internal tests
     */
    public static function reset(): void
    {
        self::$cached = null;
    }
}
