/**
 * SIMD byte scanners (SSE2 / NEON / scalar fallback).
 */
#include "monolog_parser.h"

#include <string.h>

#if defined(__x86_64__) || defined(_M_X64) || defined(__i386__) || defined(_M_IX86)
#  define MONOLOG_X86 1
#  include <immintrin.h>
#elif defined(__aarch64__) || defined(__ARM_NEON)
#  define MONOLOG_NEON 1
#  include <arm_neon.h>
#endif

const char *monolog_parser_simd(void)
{
#if defined(MONOLOG_X86)
    return "sse2";
#elif defined(MONOLOG_NEON)
    return "neon";
#else
    return "scalar";
#endif
}

/* ---- scalar helpers ---------------------------------------------------- */

static const char *memchr_scalar(const char *buf, int c, size_t len)
{
    const unsigned char *p = (const unsigned char *)buf;
    const unsigned char ch = (unsigned char)c;
    for (size_t i = 0; i < len; i++) {
        if (p[i] == ch) {
            return (const char *)(p + i);
        }
    }
    return NULL;
}

static const char *memrchr_scalar(const char *buf, int c, size_t len)
{
    const unsigned char *p = (const unsigned char *)buf;
    const unsigned char ch = (unsigned char)c;
    for (size_t i = len; i > 0; i--) {
        if (p[i - 1] == ch) {
            return (const char *)(p + i - 1);
        }
    }
    return NULL;
}

#if defined(MONOLOG_X86)

/* ---- SSE2 -------------------------------------------------------------- */

static const char *memchr_sse2(const char *buf, int c, size_t len)
{
    const unsigned char *p = (const unsigned char *)buf;
    const unsigned char ch = (unsigned char)c;
    const __m128i needle = _mm_set1_epi8((char)ch);

    while (len >= 16) {
        __m128i chunk = _mm_loadu_si128((const __m128i *)p);
        __m128i eq = _mm_cmpeq_epi8(chunk, needle);
        int mask = _mm_movemask_epi8(eq);
        if (mask) {
            return (const char *)(p + __builtin_ctz((unsigned)mask));
        }
        p += 16;
        len -= 16;
    }
    return memchr_scalar((const char *)p, c, len);
}

static const char *memrchr_sse2(const char *buf, int c, size_t len)
{
    const unsigned char *base = (const unsigned char *)buf;
    const unsigned char ch = (unsigned char)c;
    const __m128i needle = _mm_set1_epi8((char)ch);
    size_t i = len;

    while (i >= 16) {
        i -= 16;
        __m128i chunk = _mm_loadu_si128((const __m128i *)(base + i));
        __m128i eq = _mm_cmpeq_epi8(chunk, needle);
        int mask = _mm_movemask_epi8(eq);
        if (mask) {
            return (const char *)(base + i + (31 - __builtin_clz((unsigned)mask)));
        }
    }
    return memrchr_scalar((const char *)base, c, i);
}

#endif /* MONOLOG_X86 */

#if defined(MONOLOG_NEON)

static const char *memchr_neon(const char *buf, int c, size_t len)
{
    const unsigned char *p = (const unsigned char *)buf;
    const uint8x16_t needle = vdupq_n_u8((uint8_t)c);

    while (len >= 16) {
        uint8x16_t chunk = vld1q_u8(p);
        uint8x16_t eq = vceqq_u8(chunk, needle);
        uint8x8_t narrow = vshrn_n_u16(vreinterpretq_u16_u8(eq), 4);
        uint64_t mask = vget_lane_u64(vreinterpret_u64_u8(narrow), 0);
        if (mask) {
            for (int i = 0; i < 16; i++) {
                if (p[i] == (unsigned char)c) {
                    return (const char *)(p + i);
                }
            }
        }
        p += 16;
        len -= 16;
    }
    return memchr_scalar((const char *)p, c, len);
}

static const char *memrchr_neon(const char *buf, int c, size_t len)
{
    return memrchr_scalar(buf, c, len);
}

#endif /* MONOLOG_NEON */

const char *monolog_memchr(const char *buf, int c, size_t len)
{
    if (!buf || len == 0) {
        return NULL;
    }
#if defined(MONOLOG_X86)
    return memchr_sse2(buf, c, len);
#elif defined(MONOLOG_NEON)
    return memchr_neon(buf, c, len);
#else
    return memchr_scalar(buf, c, len);
#endif
}

const char *monolog_memrchr(const char *buf, int c, size_t len)
{
    if (!buf || len == 0) {
        return NULL;
    }
#if defined(MONOLOG_X86)
    return memrchr_sse2(buf, c, len);
#elif defined(MONOLOG_NEON)
    return memrchr_neon(buf, c, len);
#else
    return memrchr_scalar(buf, c, len);
#endif
}

size_t monolog_find_newlines(const char *buf, size_t len,
                             size_t *offsets, size_t max_offsets)
{
    size_t count = 0;
    const char *p = buf;
    const char *end = buf + len;

    while (p < end) {
        const char *hit = monolog_memchr(p, '\n', (size_t)(end - p));
        if (!hit) {
            break;
        }
        if (offsets && count < max_offsets) {
            offsets[count] = (size_t)(hit - buf);
        }
        count++;
        p = hit + 1;
    }
    return count;
}
