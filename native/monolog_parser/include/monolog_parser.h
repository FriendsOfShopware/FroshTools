/**
 * monolog_parser — SIMD-accelerated Monolog line parser for PHP FFI.
 *
 * Matches FroshTools LogController LINE_MATCH:
 *   /\[(?<date>.*)] (?<channel>.*)\.(?<level>DEBUG|INFO|…):(?<message>.*)/m
 *
 * Two usage modes for PHP:
 *   1) Parse a line/buffer you already hold  (monolog_parse_line / _buffer)
 *   2) Open the log file in C and yield/page parsed entries
 *        monolog_reader_open_backwards → monolog_reader_next  (iterator)
 *        monolog_file_read_backwards                         (one-shot page)
 */
#ifndef MONOLOG_PARSER_H
#define MONOLOG_PARSER_H

#include <stddef.h>
#include <stdint.h>

#ifdef __cplusplus
extern "C" {
#endif

#if defined(_WIN32) || defined(__CYGWIN__)
#  ifdef MONOLOG_PARSER_BUILD
#    define MONOLOG_API __declspec(dllexport)
#  else
#    define MONOLOG_API __declspec(dllimport)
#  endif
#else
#  define MONOLOG_API __attribute__((visibility("default")))
#endif

/** Parsed field offsets relative to the start of the input string. */
typedef struct monolog_fields {
    int32_t date_off;
    int32_t date_len;
    int32_t channel_off;
    int32_t channel_len;
    int32_t level_off;
    int32_t level_len;
    int32_t message_off;
    int32_t message_len;
    /** 1 if the Monolog pattern matched, 0 otherwise. */
    int32_t matched;
} monolog_fields_t;

/**
 * One line within a multi-line buffer (offsets relative to buffer start).
 * line_len excludes a trailing '\\n' / '\\r\\n'.
 */
typedef struct monolog_line_span {
    int32_t off;
    int32_t len;
} monolog_line_span_t;

/** Library version string (static storage). */
MONOLOG_API const char *monolog_parser_version(void);

/**
 * Which SIMD path is active: "sse2", "neon", or "scalar".
 */
MONOLOG_API const char *monolog_parser_simd(void);

/**
 * Parse a single Monolog line (need not be NUL-terminated).
 * Returns 1 on match, 0 on no match. out must be non-NULL.
 */
MONOLOG_API int monolog_parse_line(const char *line, size_t len, monolog_fields_t *out);

/**
 * Find newline byte offsets in [buf, buf+len) using SIMD scan.
 * Writes up to max_offsets offsets into offsets[]; returns count found
 * (may be > max_offsets if truncated — check return vs max).
 * If offsets is NULL, only counts.
 */
MONOLOG_API size_t monolog_find_newlines(const char *buf, size_t len,
                                         size_t *offsets, size_t max_offsets);

/**
 * Split buffer into line spans (no trailing CR/LF). Handles \\n and \\r\\n.
 * Returns number of lines written (capped by max_lines).
 */
MONOLOG_API size_t monolog_split_lines(const char *buf, size_t len,
                                       monolog_line_span_t *lines, size_t max_lines);

/**
 * Parse every line in a buffer. entries[i] fields are relative to buf.
 * Unmatched lines still get an entry with matched=0; for those the whole
 * line is reported as message (message_off/len set, others 0).
 * Returns number of lines processed (capped by max_entries).
 */
MONOLOG_API size_t monolog_parse_buffer(const char *buf, size_t len,
                                        monolog_fields_t *entries, size_t max_entries);

/**
 * SIMD memchr — find first occurrence of byte c in [buf, buf+len).
 * Returns pointer to the byte, or NULL.
 */
MONOLOG_API const char *monolog_memchr(const char *buf, int c, size_t len);

/**
 * Find last occurrence of byte c in [buf, buf+len).
 * Returns pointer to the byte, or NULL.
 */
MONOLOG_API const char *monolog_memrchr(const char *buf, int c, size_t len);

/* =====================================================================
 * File reader — open in C, yield parsed lines (newest first)
 * ===================================================================== */

typedef struct monolog_reader monolog_reader_t;

/**
 * Open path for reverse iteration (newest log line first).
 * Returns NULL on open failure.
 */
MONOLOG_API monolog_reader_t *monolog_reader_open_backwards(const char *path);

/** Close reader and free all buffers. Safe on NULL. */
MONOLOG_API void monolog_reader_close(monolog_reader_t *r);

/** Total line count (SIMD newline scan on open). */
MONOLOG_API uint64_t monolog_reader_total_lines(const monolog_reader_t *r);

/** Last error message, or NULL. */
MONOLOG_API const char *monolog_reader_error(const monolog_reader_t *r);

/**
 * Yield next line (newest-first), parse it.
 *
 * Returns:
 *   1  — entry written; *line_out points at NUL-terminated line
 *        (valid until next next()/close). fields offsets relative to *line_out.
 *   0  — EOF
 *  -1  — error (see monolog_reader_error)
 *
 * Unmatched lines: fields->matched == 0, message = full line.
 */
MONOLOG_API int monolog_reader_next(monolog_reader_t *r,
                                    monolog_fields_t *out,
                                    const char **line_out);

/**
 * Discard the next n lines (newest-first). Returns 1 ok, 0 hit EOF early, -1 error.
 */
MONOLOG_API int monolog_reader_skip(monolog_reader_t *r, uint64_t n);

/* =====================================================================
 * One-shot page — preferred for LogController offset/limit
 * ===================================================================== */

/** Owned strings; free via monolog_page_free only. */
typedef struct monolog_page_entry {
    int32_t matched;
    const char *date;
    const char *channel;
    const char *level;
    const char *message;
} monolog_page_entry_t;

typedef struct monolog_page monolog_page_t;

/**
 * Read [offset, offset+limit) lines from end of file (offset 0 = newest),
 * parse each. On success *out is allocated; free with monolog_page_free.
 * Returns 0 on success, -1 on error.
 */
MONOLOG_API int monolog_file_read_backwards(const char *path,
                                            uint64_t offset,
                                            uint64_t limit,
                                            monolog_page_t **out);

MONOLOG_API void monolog_page_free(monolog_page_t *page);

MONOLOG_API uint64_t monolog_page_total_lines(const monolog_page_t *page);
MONOLOG_API uint32_t monolog_page_count(const monolog_page_t *page);
MONOLOG_API const monolog_page_entry_t *monolog_page_entry(const monolog_page_t *page,
                                                           uint32_t index);

#ifdef __cplusplus
}
#endif

#endif /* MONOLOG_PARSER_H */
