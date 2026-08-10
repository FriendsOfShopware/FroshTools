/**
 * Micro-benchmark: SIMD monolog_parse_buffer throughput.
 */
#define _POSIX_C_SOURCE 199309L

#include "monolog_parser.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>

static double now_sec(void)
{
    struct timespec ts;
    clock_gettime(CLOCK_MONOTONIC, &ts);
    return (double)ts.tv_sec + (double)ts.tv_nsec * 1e-9;
}

static char *build_corpus(size_t lines, size_t *out_len)
{
    static const char *tpl =
        "[2024-03-15T10:30:00.%06zu+00:00] request.ERROR: Uncaught PHP Exception "
        "RuntimeException: \"Something broke at line %zu\" at /var/www/html/src/Foo.php "
        "line 42 {\"exception\":[]} []\n";
    size_t cap = lines * 256;
    char *buf = malloc(cap);
    if (!buf) {
        return NULL;
    }
    size_t used = 0;
    for (size_t i = 0; i < lines; i++) {
        char line[320];
        int n = snprintf(line, sizeof(line), tpl, i % 1000000, i);
        if (n < 0) {
            free(buf);
            return NULL;
        }
        if (used + (size_t)n > cap) {
            cap *= 2;
            char *nb = realloc(buf, cap);
            if (!nb) {
                free(buf);
                return NULL;
            }
            buf = nb;
        }
        memcpy(buf + used, line, (size_t)n);
        used += (size_t)n;
    }
    *out_len = used;
    return buf;
}

int main(int argc, char **argv)
{
    size_t lines = 100000;
    if (argc > 1) {
        lines = (size_t)strtoull(argv[1], NULL, 10);
    }

    size_t len = 0;
    char *buf = build_corpus(lines, &len);
    if (!buf) {
        fprintf(stderr, "alloc failed\n");
        return 1;
    }

    monolog_fields_t *entries = calloc(lines, sizeof(*entries));
    if (!entries) {
        free(buf);
        return 1;
    }

    printf("corpus: %zu lines, %zu bytes, simd=%s, version=%s\n",
           lines, len, monolog_parser_simd(), monolog_parser_version());

    /* warmup */
    (void)monolog_parse_buffer(buf, len, entries, lines);

    const int rounds = 20;
    double t0 = now_sec();
    size_t parsed = 0;
    for (int r = 0; r < rounds; r++) {
        parsed = monolog_parse_buffer(buf, len, entries, lines);
    }
    double t1 = now_sec();
    double elapsed = t1 - t0;
    double lines_per_sec = ((double)lines * (double)rounds) / elapsed;
    double mb_per_sec = ((double)len * (double)rounds / (1024.0 * 1024.0)) / elapsed;

    size_t matched = 0;
    for (size_t i = 0; i < parsed; i++) {
        matched += (size_t)entries[i].matched;
    }

    printf("parsed=%zu matched=%zu rounds=%d time=%.4fs\n", parsed, matched, rounds, elapsed);
    printf("throughput: %.2f M lines/s  |  %.2f MiB/s\n",
           lines_per_sec / 1e6, mb_per_sec);

    free(entries);
    free(buf);
    return 0;
}
