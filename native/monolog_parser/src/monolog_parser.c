/**
 * Monolog line parser — mirrors FroshTools PCRE LINE_MATCH semantics.
 *
 * Pattern:
 *   \[ (?<date>.*) ][ ] (?<channel>.*) \. (?<level>LEVEL) : (?<message>.*)
 *
 * channel is greedy → rightmost ".LEVEL:" wins (same as PCRE).
 */
#include "monolog_parser.h"

#include <string.h>

#define MONOLOG_PARSER_VERSION_STR "1.0.0"

typedef struct {
    const char *name;
    int len;
} level_def_t;

/* Longest first (levels are prefix-free). */
static const level_def_t LEVELS[] = {
    {"EMERGENCY", 9},
    {"CRITICAL", 8},
    {"WARNING", 7},
    {"NOTICE", 6},
    {"DEBUG", 5},
    {"ERROR", 5},
    {"ALERT", 5},
    {"INFO", 4},
};
static const size_t N_LEVELS = sizeof(LEVELS) / sizeof(LEVELS[0]);

const char *monolog_parser_version(void)
{
    return MONOLOG_PARSER_VERSION_STR;
}

static int level_at(const char *s, size_t avail, int *out_len)
{
    for (size_t i = 0; i < N_LEVELS; i++) {
        int n = LEVELS[i].len;
        if ((size_t)n + 1 > avail) {
            continue;
        }
        if (memcmp(s, LEVELS[i].name, (size_t)n) == 0 && s[n] == ':') {
            *out_len = n;
            return 1;
        }
    }
    return 0;
}

/**
 * Find rightmost ".LEVEL:" in [start, start+len).
 */
static int find_rightmost_level(const char *start, size_t len,
                                const char **level_name, int *level_len,
                                const char **dot_pos)
{
    const char *best_dot = NULL;
    const char *best_level = NULL;
    int best_llen = 0;

    const char *p = start;
    const char *end = start + len;

    while (p < end) {
        const char *dot = monolog_memchr(p, '.', (size_t)(end - p));
        if (!dot) {
            break;
        }
        size_t avail = (size_t)(end - (dot + 1));
        int llen = 0;
        if (avail > 0 && level_at(dot + 1, avail, &llen)) {
            best_dot = dot;
            best_level = dot + 1;
            best_llen = llen;
        }
        p = dot + 1;
    }

    if (!best_dot) {
        return 0;
    }
    *dot_pos = best_dot;
    *level_name = best_level;
    *level_len = best_llen;
    return 1;
}

int monolog_parse_line(const char *line, size_t len, monolog_fields_t *out)
{
    if (!out) {
        return 0;
    }
    memset(out, 0, sizeof(*out));

    if (!line || len == 0) {
        return 0;
    }

    /* Strip trailing CR (from CRLF). */
    if (line[len - 1] == '\r') {
        len--;
    }
    if (len == 0) {
        return 0;
    }

    /* 1) '[' date start */
    const char *lb = monolog_memchr(line, '[', len);
    if (!lb) {
        return 0;
    }

    /* 2) first "] " after '[' */
    const char *rb = NULL;
    {
        const char *scan = lb + 1;
        while (scan < line + len) {
            const char *cand = monolog_memchr(scan, ']', (size_t)(line + len - scan));
            if (!cand) {
                break;
            }
            if ((size_t)(line + len - cand) >= 2 && cand[1] == ' ') {
                rb = cand;
                break;
            }
            scan = cand + 1;
        }
    }
    if (!rb) {
        return 0;
    }

    const char *date_start = lb + 1;
    size_t date_len = (size_t)(rb - date_start);
    const char *body = rb + 2; /* skip "] " */
    size_t body_len = (size_t)(line + len - body);

    /* 3) rightmost ".LEVEL:" in body (PCRE greedy channel) */
    const char *level_name = NULL;
    int level_len = 0;
    const char *dot = NULL;
    if (!find_rightmost_level(body, body_len, &level_name, &level_len, &dot)) {
        return 0;
    }

    const char *channel_start = body;
    size_t channel_len = (size_t)(dot - body);
    const char *message_start = level_name + level_len + 1; /* skip "LEVEL:" */
    size_t message_len = (size_t)(line + len - message_start);

    out->date_off = (int32_t)(date_start - line);
    out->date_len = (int32_t)date_len;
    out->channel_off = (int32_t)(channel_start - line);
    out->channel_len = (int32_t)channel_len;
    out->level_off = (int32_t)(level_name - line);
    out->level_len = (int32_t)level_len;
    out->message_off = (int32_t)(message_start - line);
    out->message_len = (int32_t)message_len;
    out->matched = 1;
    return 1;
}

size_t monolog_split_lines(const char *buf, size_t len,
                           monolog_line_span_t *lines, size_t max_lines)
{
    if (!buf || !lines || max_lines == 0) {
        return 0;
    }

    size_t n = 0;
    size_t start = 0;

    while (n < max_lines) {
        const char *hit = NULL;
        if (start < len) {
            hit = monolog_memchr(buf + start, '\n', len - start);
        }
        size_t end = hit ? (size_t)(hit - buf) : len;
        size_t line_len = end - start;
        if (line_len > 0 && buf[start + line_len - 1] == '\r') {
            line_len--;
        }
        /* skip pure empty trailing line caused by final newline */
        if (!hit && line_len == 0 && n > 0) {
            break;
        }
        lines[n].off = (int32_t)start;
        lines[n].len = (int32_t)line_len;
        n++;
        if (!hit) {
            break;
        }
        start = end + 1;
        if (start >= len) {
            break;
        }
    }
    return n;
}

size_t monolog_parse_buffer(const char *buf, size_t len,
                            monolog_fields_t *entries, size_t max_entries)
{
    if (!buf || !entries || max_entries == 0) {
        return 0;
    }

    size_t produced = 0;
    size_t start = 0;

    while (produced < max_entries) {
        const char *hit = NULL;
        if (start < len) {
            hit = monolog_memchr(buf + start, '\n', len - start);
        }
        size_t end = hit ? (size_t)(hit - buf) : len;
        size_t line_len = end - start;
        if (line_len > 0 && buf[start + line_len - 1] == '\r') {
            line_len--;
        }

        if (!hit && line_len == 0 && produced > 0) {
            break;
        }
        if (start >= len && line_len == 0) {
            break;
        }

        monolog_fields_t local;
        int ok = monolog_parse_line(buf + start, line_len, &local);
        if (ok) {
            local.date_off += (int32_t)start;
            local.channel_off += (int32_t)start;
            local.level_off += (int32_t)start;
            local.message_off += (int32_t)start;
            entries[produced] = local;
        } else {
            memset(&entries[produced], 0, sizeof(entries[produced]));
            entries[produced].matched = 0;
            entries[produced].message_off = (int32_t)start;
            entries[produced].message_len = (int32_t)line_len;
        }
        produced++;

        if (!hit) {
            break;
        }
        start = end + 1;
        if (start >= len) {
            break;
        }
    }
    return produced;
}
