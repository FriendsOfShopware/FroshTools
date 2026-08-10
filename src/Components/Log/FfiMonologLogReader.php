<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Log;

/**
 * Opens the log file inside the prebuilt libmonolog_parser and yields/pages
 * parsed entries. Users do not compile — {@see NativeLibraryLocator} picks the
 * matching linux/darwin × x86_64/arm64 artifact from native/lib/.
 */
final class FfiMonologLogReader implements MonologLogReaderInterface
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

typedef struct monolog_page_entry {
    int32_t matched;
    const char *date;
    const char *channel;
    const char *level;
    const char *message;
} monolog_page_entry_t;

typedef struct monolog_reader monolog_reader_t;
typedef struct monolog_page monolog_page_t;

const char *monolog_parser_version(void);
const char *monolog_parser_simd(void);

int monolog_parse_line(const char *line, size_t len, monolog_fields_t *out);

monolog_reader_t *monolog_reader_open_backwards(const char *path);
void monolog_reader_close(monolog_reader_t *r);
uint64_t monolog_reader_total_lines(const monolog_reader_t *r);
const char *monolog_reader_error(const monolog_reader_t *r);
int monolog_reader_next(monolog_reader_t *r, monolog_fields_t *out, const char **line_out);
int monolog_reader_skip(monolog_reader_t *r, uint64_t n);

int monolog_file_read_backwards(const char *path, uint64_t offset, uint64_t limit, monolog_page_t **out);
void monolog_page_free(monolog_page_t *page);
uint64_t monolog_page_total_lines(const monolog_page_t *page);
uint32_t monolog_page_count(const monolog_page_t *page);
const monolog_page_entry_t *monolog_page_entry(const monolog_page_t *page, uint32_t index);
CDEF;

    private \FFI $ffi;

    private function __construct(\FFI $ffi)
    {
        $this->ffi = $ffi;
    }

    /**
     * @throws \Throwable
     */
    public static function create(?string $libraryPath = null): self
    {
        if (!\extension_loaded('ffi') || !class_exists(\FFI::class, false)) {
            throw new \RuntimeException('ext-ffi is not available');
        }

        $path = $libraryPath ?? NativeLibraryLocator::locate();
        if ($path === null || !is_readable($path)) {
            throw new \RuntimeException(
                'libmonolog_parser not found for ' . NativeLibraryLocator::describe()
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

    public function readBackwards(string $filePath, int $offset, int $limit): array
    {
        /** @var \FFI\CData $pagePtr */
        $pagePtr = $this->ffi->new('monolog_page_t*');
        $rc = $this->ffi->monolog_file_read_backwards(
            $filePath,
            (int) max(0, $offset),
            (int) max(0, $limit),
            \FFI::addr($pagePtr),
        );

        if ($rc !== 0 || $pagePtr === null) {
            throw new \RuntimeException(\sprintf('monolog_file_read_backwards failed for %s', $filePath));
        }

        try {
            $total = (int) $this->ffi->monolog_page_total_lines($pagePtr);
            $count = (int) $this->ffi->monolog_page_count($pagePtr);
            $entries = [];

            for ($i = 0; $i < $count; $i++) {
                $entry = $this->ffi->monolog_page_entry($pagePtr, $i);
                if ($entry === null) {
                    continue;
                }
                $entries[] = [
                    'date' => (string) $entry->date,
                    'channel' => (string) $entry->channel,
                    'level' => (string) $entry->level,
                    'message' => (string) $entry->message,
                ];
            }

            return [
                'entries' => $entries,
                'total' => $total,
            ];
        } finally {
            $this->ffi->monolog_page_free($pagePtr);
        }
    }

    public function yieldBackwards(string $filePath): \Generator
    {
        $reader = $this->ffi->monolog_reader_open_backwards($filePath);
        if ($reader === null) {
            throw new \RuntimeException(\sprintf('Cannot open log file: %s', $filePath));
        }

        try {
            /** @var \FFI\CData $fields */
            $fields = $this->ffi->new('monolog_fields_t');
            /** @var \FFI\CData $linePtr */
            $linePtr = $this->ffi->new('const char*');

            while (true) {
                $rc = $this->ffi->monolog_reader_next($reader, \FFI::addr($fields), \FFI::addr($linePtr));
                if ($rc === 0) {
                    break;
                }
                if ($rc < 0) {
                    $err = $this->ffi->monolog_reader_error($reader);
                    throw new \RuntimeException('monolog_reader_next: ' . ($err ?? 'unknown error'));
                }

                $line = \FFI::string($linePtr);
                if ($fields->matched === 1) {
                    yield [
                        'date' => substr($line, $fields->date_off, $fields->date_len),
                        'channel' => substr($line, $fields->channel_off, $fields->channel_len),
                        'level' => substr($line, $fields->level_off, $fields->level_len),
                        'message' => substr($line, $fields->message_off, $fields->message_len),
                    ];
                } else {
                    yield [
                        'date' => 'unknown',
                        'channel' => 'unknown',
                        'level' => 'unknown',
                        'message' => $line,
                    ];
                }
            }
        } finally {
            $this->ffi->monolog_reader_close($reader);
        }
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
}
