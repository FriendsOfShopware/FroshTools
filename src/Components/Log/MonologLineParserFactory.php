<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Log;

/**
 * Prefers the SIMD C library via FFI; falls back to PCRE.
 */
final class MonologLineParserFactory
{
    private static ?MonologLineParserInterface $cached = null;

    public function __invoke(): MonologLineParserInterface
    {
        return self::create();
    }

    public static function create(bool $forcePreg = false): MonologLineParserInterface
    {
        if ($forcePreg) {
            return new PregMonologParser();
        }

        if (self::$cached !== null) {
            return self::$cached;
        }

        $ffi = FfiMonologParser::tryCreate();
        self::$cached = $ffi ?? new PregMonologParser();

        return self::$cached;
    }

    /** @internal tests */
    public static function reset(): void
    {
        self::$cached = null;
    }
}
