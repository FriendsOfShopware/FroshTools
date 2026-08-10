/**
 * Unit tests for monolog_parser — line parse + reverse file reader.
 */
#define _POSIX_C_SOURCE 200809L

#include "monolog_parser.h"

#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <unistd.h>
#include <fcntl.h>

static int failures = 0;

#define EXPECT(cond, msg) do { \
    if (!(cond)) { \
        fprintf(stderr, "FAIL %s:%d: %s\n", __FILE__, __LINE__, msg); \
        failures++; \
    } \
} while (0)

static int streq_span(const char *base, int32_t off, int32_t len, const char *expect)
{
    size_t elen = strlen(expect);
    if ((size_t)len != elen) {
        return 0;
    }
    return memcmp(base + off, expect, elen) == 0;
}

static void test_basic(void)
{
    const char *line =
        "[2024-03-15T10:30:00.123456+00:00] request.ERROR: Uncaught PHP Exception [] []";
    monolog_fields_t f;
    int ok = monolog_parse_line(line, strlen(line), &f);
    EXPECT(ok == 1, "basic match");
    EXPECT(f.matched == 1, "matched flag");
    EXPECT(streq_span(line, f.date_off, f.date_len, "2024-03-15T10:30:00.123456+00:00"), "date");
    EXPECT(streq_span(line, f.channel_off, f.channel_len, "request"), "channel");
    EXPECT(streq_span(line, f.level_off, f.level_len, "ERROR"), "level");
    EXPECT(streq_span(line, f.message_off, f.message_len, " Uncaught PHP Exception [] []"), "message");
}

static void test_dotted_channel(void)
{
    const char *line =
        "[2024-03-15T10:29:59.000000+00:00] doctrine.dbal.INFO: Connecting [] []";
    monolog_fields_t f;
    int ok = monolog_parse_line(line, strlen(line), &f);
    EXPECT(ok == 1, "dotted channel match");
    EXPECT(streq_span(line, f.channel_off, f.channel_len, "doctrine.dbal"), "channel doctrine.dbal");
    EXPECT(streq_span(line, f.level_off, f.level_len, "INFO"), "level INFO");
}

static void test_rightmost_level(void)
{
    const char *line =
        "[2024-01-01T00:00:00.000000+00:00] app.INFO: Failed with .ERROR: boom";
    monolog_fields_t f;
    int ok = monolog_parse_line(line, strlen(line), &f);
    EXPECT(ok == 1, "rightmost match");
    EXPECT(streq_span(line, f.channel_off, f.channel_len, "app.INFO: Failed with "), "greedy channel");
    EXPECT(streq_span(line, f.level_off, f.level_len, "ERROR"), "rightmost level ERROR");
    EXPECT(streq_span(line, f.message_off, f.message_len, " boom"), "message");
}

static void test_all_levels(void)
{
    const char *levels[] = {
        "DEBUG", "INFO", "NOTICE", "WARNING", "ERROR", "CRITICAL", "ALERT", "EMERGENCY"
    };
    char buf[256];
    for (size_t i = 0; i < sizeof(levels) / sizeof(levels[0]); i++) {
        snprintf(buf, sizeof(buf),
                 "[2024-01-01T00:00:00+00:00] ch.%s: msg", levels[i]);
        monolog_fields_t f;
        int ok = monolog_parse_line(buf, strlen(buf), &f);
        EXPECT(ok == 1, levels[i]);
        EXPECT(streq_span(buf, f.level_off, f.level_len, levels[i]), levels[i]);
    }
}

static void test_unmatched(void)
{
    const char *line = "not a monolog line";
    monolog_fields_t f;
    int ok = monolog_parse_line(line, strlen(line), &f);
    EXPECT(ok == 0, "unmatched");
    EXPECT(f.matched == 0, "matched=0");
}

static void test_buffer(void)
{
    const char *buf =
        "[2024-01-01T00:00:00+00:00] app.INFO: one\n"
        "garbage line\n"
        "[2024-01-01T00:00:01+00:00] app.ERROR: two\n";
    monolog_fields_t entries[8];
    size_t n = monolog_parse_buffer(buf, strlen(buf), entries, 8);
    EXPECT(n == 3, "3 lines");
    EXPECT(entries[0].matched == 1, "line0 match");
    EXPECT(entries[1].matched == 0, "line1 no match");
    EXPECT(entries[2].matched == 1, "line2 match");
    EXPECT(streq_span(buf, entries[0].level_off, entries[0].level_len, "INFO"), "line0 INFO");
    EXPECT(streq_span(buf, entries[2].level_off, entries[2].level_len, "ERROR"), "line2 ERROR");
    EXPECT(streq_span(buf, entries[1].message_off, entries[1].message_len, "garbage line"), "garbage as message");
}

static void test_newlines_simd(void)
{
    size_t cap = 1 << 16;
    char *buf = malloc(cap);
    EXPECT(buf != NULL, "alloc");
    if (!buf) {
        return;
    }
    memset(buf, 'x', cap);
    size_t expect = 0;
    for (size_t i = 100; i < cap; i += 97) {
        buf[i] = '\n';
        expect++;
    }
    size_t offsets[1024];
    size_t found = monolog_find_newlines(buf, cap, offsets, 1024);
    EXPECT(found == expect, "newline count");
    free(buf);
}

static void test_memrchr(void)
{
    const char *s = "a.b.c.ERROR:x";
    const char *p = monolog_memrchr(s, '.', strlen(s));
    EXPECT(p != NULL && p == s + 5, "memrchr last dot");
}

static char *write_temp_log(const char *contents, size_t len)
{
    char tmpl[] = "/tmp/monolog_test_XXXXXX";
    int fd = mkstemp(tmpl);
    if (fd < 0) {
        perror("mkstemp");
        return NULL;
    }
    if (len > 0) {
        ssize_t w = write(fd, contents, len);
        if (w < 0 || (size_t)w != len) {
            perror("write");
            close(fd);
            unlink(tmpl);
            return NULL;
        }
    }
    close(fd);
    return strdup(tmpl);
}

static void test_reader_yield_order(void)
{
    const char *body =
        "[2024-01-01T00:00:00+00:00] app.INFO: first\n"
        "[2024-01-01T00:00:01+00:00] app.WARNING: second\n"
        "[2024-01-01T00:00:02+00:00] app.ERROR: third\n";
    char *path = write_temp_log(body, strlen(body));
    EXPECT(path != NULL, "temp path");
    if (!path) {
        return;
    }

    monolog_reader_t *r = monolog_reader_open_backwards(path);
    EXPECT(r != NULL, "open");
    if (!r) {
        unlink(path);
        free(path);
        return;
    }

    EXPECT(monolog_reader_total_lines(r) == 3, "total 3");

    monolog_fields_t f;
    const char *line = NULL;
    int rc;

    rc = monolog_reader_next(r, &f, &line);
    EXPECT(rc == 1 && f.matched == 1, "yield 1");
    EXPECT(streq_span(line, f.level_off, f.level_len, "ERROR"), "newest ERROR");
    EXPECT(streq_span(line, f.message_off, f.message_len, " third"), "newest msg");

    rc = monolog_reader_next(r, &f, &line);
    EXPECT(rc == 1 && streq_span(line, f.level_off, f.level_len, "WARNING"), "second WARNING");

    rc = monolog_reader_next(r, &f, &line);
    EXPECT(rc == 1 && streq_span(line, f.level_off, f.level_len, "INFO"), "oldest INFO");

    rc = monolog_reader_next(r, &f, &line);
    EXPECT(rc == 0, "eof");

    monolog_reader_close(r);
    unlink(path);
    free(path);
}

static void test_reader_page_offset_limit(void)
{
    const char *body =
        "[2024-01-01T00:00:00+00:00] app.INFO: L0\n"
        "[2024-01-01T00:00:01+00:00] app.INFO: L1\n"
        "[2024-01-01T00:00:02+00:00] app.INFO: L2\n"
        "[2024-01-01T00:00:03+00:00] app.INFO: L3\n"
        "[2024-01-01T00:00:04+00:00] app.INFO: L4\n";
    char *path = write_temp_log(body, strlen(body));
    EXPECT(path != NULL, "temp path");
    if (!path) {
        return;
    }

    monolog_page_t *page = NULL;
    int rc = monolog_file_read_backwards(path, 1, 2, &page);
    EXPECT(rc == 0 && page != NULL, "page ok");
    if (!page) {
        unlink(path);
        free(path);
        return;
    }

    EXPECT(monolog_page_total_lines(page) == 5, "total 5");
    EXPECT(monolog_page_count(page) == 2, "count 2");

    const monolog_page_entry_t *e0 = monolog_page_entry(page, 0);
    const monolog_page_entry_t *e1 = monolog_page_entry(page, 1);
    EXPECT(e0 && strcmp(e0->message, " L3") == 0, "page[0]=L3");
    EXPECT(e1 && strcmp(e1->message, " L2") == 0, "page[1]=L2");

    monolog_page_free(page);
    unlink(path);
    free(path);
}

static void test_reader_large_multichunk(void)
{
    size_t lines = 2000;
    size_t cap = lines * 120;
    char *body = malloc(cap);
    EXPECT(body != NULL, "alloc body");
    if (!body) {
        return;
    }
    size_t used = 0;
    for (size_t i = 0; i < lines; i++) {
        int n = snprintf(body + used, cap - used,
                         "[2024-01-01T00:00:00+%04zu] ch.INFO: line-%zu-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx\n",
                         i, i);
        if (n < 0 || (size_t)n >= cap - used) {
            break;
        }
        used += (size_t)n;
    }

    char *path = write_temp_log(body, used);
    free(body);
    EXPECT(path != NULL, "temp large");
    if (!path) {
        return;
    }

    monolog_page_t *page = NULL;
    int rc = monolog_file_read_backwards(path, 0, 5, &page);
    EXPECT(rc == 0 && page != NULL, "large page");
    if (page) {
        EXPECT(monolog_page_total_lines(page) == lines, "large total");
        EXPECT(monolog_page_count(page) == 5, "large count 5");
        char expect[64];
        snprintf(expect, sizeof(expect), " line-%zu-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx", lines - 1);
        const monolog_page_entry_t *e0 = monolog_page_entry(page, 0);
        EXPECT(e0 && strcmp(e0->message, expect) == 0, "newest message");
        monolog_page_free(page);
    }

    monolog_reader_t *r = monolog_reader_open_backwards(path);
    EXPECT(r != NULL, "large open");
    if (r) {
        uint64_t n = 0;
        monolog_fields_t f;
        const char *line = NULL;
        while (monolog_reader_next(r, &f, &line) == 1) {
            n++;
        }
        EXPECT(n == lines, "yielded all lines");
        monolog_reader_close(r);
    }

    unlink(path);
    free(path);
}

int main(void)
{
    printf("monolog_parser %s  simd=%s\n",
           monolog_parser_version(), monolog_parser_simd());

    test_basic();
    test_dotted_channel();
    test_rightmost_level();
    test_all_levels();
    test_unmatched();
    test_buffer();
    test_newlines_simd();
    test_memrchr();
    test_reader_yield_order();
    test_reader_page_offset_limit();
    test_reader_large_multichunk();

    if (failures) {
        fprintf(stderr, "\n%d failure(s)\n", failures);
        return 1;
    }
    printf("All tests passed.\n");
    return 0;
}
