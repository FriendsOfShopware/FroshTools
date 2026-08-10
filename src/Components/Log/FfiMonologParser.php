<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Log;

/**
 * PHP FFI binding for prebuilt native/lib/libmonolog_parser-*.{so,dylib}.
 *
 * Field offsets returned by the C library are relative to the input line.
 * Falls through construction if FFI is unavailable or the .so cannot be loaded;
 * use {@see MonologLineParserFactory} which always returns a working parser.
 */
final class FfiMonologParser implements MonologLineParserInterface
{
    private const CDEF = <<<'CDEF'
typedef struct monolog_fields {
    int32_t date_off;
    int32_t date_len;
    int32_t channel_off;
    int32_t channel_len;
    int32_t level_off;
    int32_t level_len;
    int32_t message_off;
    int32_t message_len;
    int32_t matched;
} monolog_fields_t;

const char *monolog_parser_version(void);
const char *monolog_parser_simd(void);
int monolog_parse_line(const char *line, size_t len, monolog_fields_t *out);
CDEF;

    private \FFI $ffi;

    private function __construct(\FFI $ffi)
    {
        $this->ffi = $ffi;
    }

    /**
     * @throws \Throwable when FFI/.so cannot be used
     */
    public static function create(?string $libraryPath = null): self
    {
        if (!\extension_loaded('ffi')) {
            throw new \RuntimeException('ext-ffi is not loaded');
        }

        if (!class_exists(\FFI::class, false)) {
            throw new \RuntimeException('FFI class unavailable');
        }

        $path = $libraryPath ?? NativeLibraryLocator::locate();
        if ($path === null || !is_readable($path)) {
            throw new \RuntimeException(
                'libmonolog_parser not found for ' . NativeLibraryLocator::describe()
                . ' — ship prebuilds under native/lib/ or set MONOLOG_PARSER_LIB'
            );
        }

        /** @var \FFI $ffi */
        $ffi = \FFI::cdef(self::CDEF, $path);

        return new self($ffi);
    }

    public static function tryCreate(?string $libraryPath = null): ?self
    {
        try {
            return self::create($libraryPath);
        } catch (\Throwable) {
            return null;
        }
    }

    public function parse(string $line): array
    {
        $len = \strlen($line);
        /** @var \FFI\CData $fields */
        $fields = $this->ffi->new('monolog_fields_t');
        $ok = $this->ffi->monolog_parse_line($line, $len, \FFI::addr($fields));

        if ($ok !== 1 || $fields->matched !== 1) {
            return [
                'message' => $line,
                'channel' => 'unknown',
                'date' => 'unknown',
                'level' => 'unknown',
            ];
        }

        return [
            'date' => substr($line, $fields->date_off, $fields->date_len),
            'channel' => substr($line, $fields->channel_off, $fields->channel_len),
            'level' => substr($line, $fields->level_off, $fields->level_len),
            'message' => substr($line, $fields->message_off, $fields->message_len),
        ];
    }

    public function backend(): string
    {
        return 'ffi';
    }

    public function simd(): string
    {
        return (string) $this->ffi->monolog_parser_simd();
    }

    public function version(): string
    {
        return (string) $this->ffi->monolog_parser_version();
    }

    /** @deprecated use NativeLibraryLocator::locate() */
    public static function detectLibraryPath(): ?string
    {
        return NativeLibraryLocator::locate();
    }
}
