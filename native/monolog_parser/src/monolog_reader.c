/**
 * Reverse log file reader — open file in C, yield/page parsed lines for PHP FFI.
 *
 * Chunked reverse scan mirrors Frosh\Tools\Components\LineReader::readLinesBackwards,
 * then monolog_parse_line on each yielded line.
 */
#include "monolog_parser.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#define CHUNK_SIZE 65536

struct monolog_reader {
    FILE *fp;
    size_t file_size;
    size_t pos; /* byte offset of next older unread region start */

    char *chunk;
    size_t chunk_cap;

    /* buffer[0] from LineReader — incomplete oldest fragment, or sole remaining line */
    char *head;
    size_t head_len;
    size_t head_cap;
    int has_head;

    /* complete lines ready to yield, oldest→newest; pop from end */
    char **ready;
    size_t ready_n;
    size_t ready_cap;

    char *current;
    size_t current_cap;

    uint64_t total_lines;
    int first_chunk;
    int eof;
    int err;
    char errbuf[128];
};

struct monolog_page {
    uint64_t total_lines;
    uint32_t count;
    monolog_page_entry_t *entries;
};

static int set_err(monolog_reader_t *r, const char *msg)
{
    r->err = 1;
    snprintf(r->errbuf, sizeof(r->errbuf), "%s", msg);
    return -1;
}

static int ready_push(monolog_reader_t *r, char *line)
{
    if (r->ready_n + 1 > r->ready_cap) {
        size_t ncap = r->ready_cap ? r->ready_cap * 2 : 16;
        char **nr = (char **)realloc(r->ready, ncap * sizeof(char *));
        if (!nr) {
            free(line);
            return set_err(r, "oom ready");
        }
        r->ready = nr;
        r->ready_cap = ncap;
    }
    r->ready[r->ready_n++] = line;
    return 0;
}

static int set_head(monolog_reader_t *r, const char *s, size_t len)
{
    if (r->head_cap < len + 1) {
        free(r->head);
        r->head = (char *)malloc(len + 1);
        if (!r->head) {
            return set_err(r, "oom head");
        }
        r->head_cap = len + 1;
    }
    memcpy(r->head, s, len);
    r->head[len] = '\0';
    r->head_len = len;
    r->has_head = 1;
    return 0;
}

static char *dup_slice(const char *s, size_t len)
{
    char *p = (char *)malloc(len + 1);
    if (!p) {
        return NULL;
    }
    memcpy(p, s, len);
    p[len] = '\0';
    return p;
}

static uint64_t count_lines_fp(FILE *fp, size_t file_size)
{
    if (file_size == 0) {
        return 0;
    }

    char buf[CHUNK_SIZE];
    uint64_t newlines = 0;
    size_t got_total = 0;
    int last_was_nl = 0;

    if (fseek(fp, 0, SEEK_SET) != 0) {
        return 0;
    }

    while (got_total < file_size) {
        size_t want = file_size - got_total;
        if (want > sizeof(buf)) {
            want = sizeof(buf);
        }
        size_t n = fread(buf, 1, want, fp);
        if (n == 0) {
            break;
        }
        newlines += (uint64_t)monolog_find_newlines(buf, n, NULL, 0);
        last_was_nl = (buf[n - 1] == '\n');
        got_total += n;
    }

    if (!last_was_nl) {
        newlines += 1;
    }
    return newlines;
}

/**
 * Explode `data` by '\\n' into ready[] + head, matching LineReader:
 *  - first chunk: strip one trailing '\\n'
 *  - if has_head: data is older_chunk + head
 *  - all parts go into a temporary list; last part becomes head if pos>0
 *    wait — LineReader keeps buffer as full explode; yields when count>1 from end,
 *    only buffer[0] is held when needing more. So after explode L0..Lk:
 *    ready gets L1..Lk (to be popped from end = Lk first), head = L0 if more data
 *    or if pos==0 everything including L0 is ready.
 */
static int explode_into_reader(monolog_reader_t *r, char *data, size_t len, int at_file_start)
{
    /* strip one trailing newline on first chunk only (LineReader) */
    if (r->first_chunk) {
        r->first_chunk = 0;
        if (len > 0 && data[len - 1] == '\n') {
            len--;
            if (len > 0 && data[len - 1] == '\r') {
                len--;
            }
        }
    }

    size_t start = 0;
    size_t part_count = 0;
    /* first pass count */
    for (size_t i = 0; i < len; i++) {
        if (data[i] == '\n') {
            part_count++;
        }
    }
    part_count += 1; /* last segment */

    char **parts = (char **)calloc(part_count, sizeof(char *));
    if (!parts) {
        return set_err(r, "oom parts");
    }

    size_t pi = 0;
    for (size_t i = 0; i <= len; i++) {
        if (i == len || data[i] == '\n') {
            size_t plen = i - start;
            if (plen > 0 && data[start + plen - 1] == '\r') {
                plen--;
            }
            parts[pi] = dup_slice(data + start, plen);
            if (!parts[pi]) {
                for (size_t j = 0; j < pi; j++) {
                    free(parts[j]);
                }
                free(parts);
                return set_err(r, "oom part");
            }
            pi++;
            start = i + 1;
        }
    }

    /*
     * parts[0] .. parts[pi-1] are oldest → newest within this combined chunk.
     * If not at file start, parts[0] may be incomplete → becomes head.
     * Remaining parts are complete lines → push to ready (still oldest→newest).
     */
    size_t from = 0;
    if (!at_file_start) {
        /* keep parts[0] as head for next older merge */
        if (set_head(r, parts[0], strlen(parts[0])) != 0) {
            for (size_t j = 0; j < pi; j++) {
                free(parts[j]);
            }
            free(parts);
            return -1;
        }
        free(parts[0]);
        from = 1;
    } else {
        r->has_head = 0;
    }

    for (size_t j = from; j < pi; j++) {
        if (ready_push(r, parts[j]) != 0) {
            for (size_t k = j + 1; k < pi; k++) {
                free(parts[k]);
            }
            free(parts);
            return -1;
        }
        /* ownership transferred */
    }
    free(parts);
    return 0;
}

static int load_older_chunk(monolog_reader_t *r)
{
    if (r->pos == 0) {
        /* flush head as final line */
        if (r->has_head) {
            char *line = dup_slice(r->head, r->head_len);
            if (!line) {
                return set_err(r, "oom head flush");
            }
            r->has_head = 0;
            if (ready_push(r, line) != 0) {
                return -1;
            }
            return 0;
        }
        r->eof = 1;
        return 0;
    }

    size_t nread = r->pos > CHUNK_SIZE ? CHUNK_SIZE : r->pos;
    size_t start = r->pos - nread;

    if (r->chunk_cap < nread + (r->has_head ? r->head_len : 0) + 1) {
        free(r->chunk);
        r->chunk_cap = nread + (r->has_head ? r->head_len : 0) + 1;
        r->chunk = (char *)malloc(r->chunk_cap);
        if (!r->chunk) {
            return set_err(r, "oom chunk");
        }
    }

    if (fseek(r->fp, (long)start, SEEK_SET) != 0) {
        return set_err(r, "fseek failed");
    }
    size_t got = fread(r->chunk, 1, nread, r->fp);
    if (got != nread) {
        return set_err(r, "fread failed");
    }

    /* combine older_chunk + head  (LineReader: chunk . buffer[0]) */
    size_t combined_len = got;
    if (r->has_head) {
        memcpy(r->chunk + got, r->head, r->head_len);
        combined_len = got + r->head_len;
        r->has_head = 0;
    }
    r->chunk[combined_len] = '\0';

    r->pos = start;
    int at_start = (r->pos == 0);

    return explode_into_reader(r, r->chunk, combined_len, at_start);
}

static int ensure_ready(monolog_reader_t *r)
{
    while (r->ready_n == 0 && !r->eof && !r->err) {
        /*
         * LineReader yields when count(buffer) > 1, else loads more.
         * We only keep complete lines in ready[]; head is incomplete.
         * So ready_n==0 ⇒ need older chunk (or flush head at pos==0).
         */
        if (load_older_chunk(r) != 0) {
            return -1;
        }
    }
    return r->err ? -1 : 0;
}

/* ---- public ----------------------------------------------------------- */

monolog_reader_t *monolog_reader_open_backwards(const char *path)
{
    if (!path) {
        return NULL;
    }

    monolog_reader_t *r = (monolog_reader_t *)calloc(1, sizeof(*r));
    if (!r) {
        return NULL;
    }

    r->fp = fopen(path, "rb");
    if (!r->fp) {
        free(r);
        return NULL;
    }

    if (fseek(r->fp, 0, SEEK_END) != 0) {
        fclose(r->fp);
        free(r);
        return NULL;
    }
    long sz = ftell(r->fp);
    if (sz < 0) {
        fclose(r->fp);
        free(r);
        return NULL;
    }

    r->file_size = (size_t)sz;
    r->pos = r->file_size;
    r->first_chunk = 1;
    r->total_lines = count_lines_fp(r->fp, r->file_size);

    if (r->file_size == 0) {
        r->eof = 1;
    }

    return r;
}

void monolog_reader_close(monolog_reader_t *r)
{
    if (!r) {
        return;
    }
    if (r->fp) {
        fclose(r->fp);
    }
    free(r->chunk);
    free(r->head);
    free(r->current);
    for (size_t i = 0; i < r->ready_n; i++) {
        free(r->ready[i]);
    }
    free(r->ready);
    free(r);
}

uint64_t monolog_reader_total_lines(const monolog_reader_t *r)
{
    return r ? r->total_lines : 0;
}

const char *monolog_reader_error(const monolog_reader_t *r)
{
    if (!r || !r->err) {
        return NULL;
    }
    return r->errbuf;
}

int monolog_reader_next(monolog_reader_t *r, monolog_fields_t *out, const char **line_out)
{
    if (!r || !out || !line_out) {
        return -1;
    }
    if (r->err) {
        return -1;
    }

    /* Prefer ready lines; if only head and more file, LineReader would load more
       before yielding head — ensure_ready handles that. If ready empty and head
       is sole line with pos==0, load_older flushes head into ready. */
    if (ensure_ready(r) != 0) {
        return -1;
    }

    /* LineReader: if count > 1 pop; if count == 1 and pos==0 yield last.
       We never put incomplete head into ready, so pop any ready line. */
    if (r->ready_n == 0) {
        return 0;
    }

    char *line = r->ready[--r->ready_n];
    size_t len = strlen(line);

    if (r->current_cap < len + 1) {
        free(r->current);
        r->current = (char *)malloc(len + 1);
        if (!r->current) {
            free(line);
            set_err(r, "oom current");
            return -1;
        }
        r->current_cap = len + 1;
    }
    memcpy(r->current, line, len + 1);
    free(line);

    memset(out, 0, sizeof(*out));
    if (!monolog_parse_line(r->current, len, out)) {
        out->matched = 0;
        out->message_off = 0;
        out->message_len = (int32_t)len;
    }

    *line_out = r->current;
    return 1;
}

int monolog_reader_skip(monolog_reader_t *r, uint64_t n)
{
    if (!r) {
        return -1;
    }
    monolog_fields_t tmp;
    const char *line = NULL;
    for (uint64_t i = 0; i < n; i++) {
        int rc = monolog_reader_next(r, &tmp, &line);
        if (rc <= 0) {
            return rc;
        }
    }
    return 1;
}

static char *xstrndup(const char *s, size_t n)
{
    char *p = (char *)malloc(n + 1);
    if (!p) {
        return NULL;
    }
    memcpy(p, s, n);
    p[n] = '\0';
    return p;
}

int monolog_file_read_backwards(const char *path, uint64_t offset, uint64_t limit,
                                monolog_page_t **out)
{
    if (!path || !out) {
        return -1;
    }
    *out = NULL;

    monolog_reader_t *r = monolog_reader_open_backwards(path);
    if (!r) {
        return -1;
    }

    monolog_page_t *page = (monolog_page_t *)calloc(1, sizeof(*page));
    if (!page) {
        monolog_reader_close(r);
        return -1;
    }
    page->total_lines = monolog_reader_total_lines(r);

    if (limit == 0) {
        monolog_reader_close(r);
        *out = page;
        return 0;
    }

    if (offset > 0) {
        int src = monolog_reader_skip(r, offset);
        if (src < 0) {
            monolog_page_free(page);
            monolog_reader_close(r);
            return -1;
        }
    }

    monolog_page_entry_t *entries =
        (monolog_page_entry_t *)calloc((size_t)limit, sizeof(*entries));
    if (!entries) {
        monolog_page_free(page);
        monolog_reader_close(r);
        return -1;
    }

    uint32_t n = 0;
    for (uint64_t i = 0; i < limit; i++) {
        monolog_fields_t f;
        const char *line = NULL;
        int rc = monolog_reader_next(r, &f, &line);
        if (rc < 0) {
            for (uint32_t j = 0; j < n; j++) {
                free((void *)entries[j].date);
                free((void *)entries[j].channel);
                free((void *)entries[j].level);
                free((void *)entries[j].message);
            }
            free(entries);
            monolog_page_free(page);
            monolog_reader_close(r);
            return -1;
        }
        if (rc == 0) {
            break;
        }

        monolog_page_entry_t *e = &entries[n];
        e->matched = f.matched;
        if (f.matched) {
            e->date = xstrndup(line + f.date_off, (size_t)f.date_len);
            e->channel = xstrndup(line + f.channel_off, (size_t)f.channel_len);
            e->level = xstrndup(line + f.level_off, (size_t)f.level_len);
            e->message = xstrndup(line + f.message_off, (size_t)f.message_len);
        } else {
            e->date = xstrndup("unknown", 7);
            e->channel = xstrndup("unknown", 7);
            e->level = xstrndup("unknown", 7);
            e->message = xstrndup(line, strlen(line));
        }
        if (!e->date || !e->channel || !e->level || !e->message) {
            free((void *)e->date);
            free((void *)e->channel);
            free((void *)e->level);
            free((void *)e->message);
            for (uint32_t j = 0; j < n; j++) {
                free((void *)entries[j].date);
                free((void *)entries[j].channel);
                free((void *)entries[j].level);
                free((void *)entries[j].message);
            }
            free(entries);
            monolog_page_free(page);
            monolog_reader_close(r);
            return -1;
        }
        n++;
    }

    page->entries = entries;
    page->count = n;
    monolog_reader_close(r);
    *out = page;
    return 0;
}

void monolog_page_free(monolog_page_t *page)
{
    if (!page) {
        return;
    }
    if (page->entries) {
        for (uint32_t i = 0; i < page->count; i++) {
            free((void *)page->entries[i].date);
            free((void *)page->entries[i].channel);
            free((void *)page->entries[i].level);
            free((void *)page->entries[i].message);
        }
        free(page->entries);
    }
    free(page);
}

uint64_t monolog_page_total_lines(const monolog_page_t *page)
{
    return page ? page->total_lines : 0;
}

uint32_t monolog_page_count(const monolog_page_t *page)
{
    return page ? page->count : 0;
}

const monolog_page_entry_t *monolog_page_entry(const monolog_page_t *page, uint32_t index)
{
    if (!page || index >= page->count) {
        return NULL;
    }
    return &page->entries[index];
}
