/*
  +----------------------------------------------------------------------+
  | Copyright (c) 2025-2026, Ilia Alshanetsky                            |
  | Copyright (c) 2025-2026, Advanced Internet Designs Inc.              |
  +----------------------------------------------------------------------+
  | This source file is subject to the BSD 3-Clause license that is      |
  | bundled with this package in the file LICENSE.                       |
  +----------------------------------------------------------------------+
  | Author: Ilia Alshanetsky <ilia@ilia.ws>                              |
  +----------------------------------------------------------------------+
*/

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "ext/standard/md5.h"
#include "ext/standard/sha1.h"
#include "ext/date/php_date.h"
#include "ext/json/php_json.h"
#include "ext/spl/spl_exceptions.h"
#include "Zend/zend_exceptions.h"
#include "Zend/zend_interfaces.h"
#include "php_fast_uuid.h"
#include "fast_uuid_arginfo.h"

#if PHP_VERSION_ID >= 80300
# include "ext/random/php_random.h"
#else
# include "ext/standard/php_random.h"
#endif

#include <time.h>
#include <string.h>
#include <stdlib.h>
#include <errno.h>
#include <stdint.h>

#ifndef PHP_WIN32
# include <unistd.h>   /* getuid/getgid for uuid2 auto local-identifier */
# include <pthread.h>  /* pthread_atfork: invalidate the CSPRNG buffer in a forked child */
#else
# include <windows.h>  /* GetSystemTimePreciseAsFileTime: wall clock, no POSIX clock_gettime */
#endif

#if (defined(__x86_64__) || defined(__i386__) || defined(_M_X64) || defined(_M_IX86)) && !defined(FU_DISABLE_SSSE3)
# include <immintrin.h>
# if defined(_MSC_VER)
#  include <intrin.h>
# else
#  include <cpuid.h>
# endif
# define FU_X86 1
# if defined(_MSC_VER)
#  define FU_TARGET_SSSE3
# else
#  define FU_TARGET_SSSE3 __attribute__((target("ssse3")))
# endif
static int fu_has_ssse3 = 0;   /* set in MINIT via an explicit CPUID probe */

/* Direct CPUID avoids libgcc's __cpu_model and its -shared relocation
   failure under toolchains such as zig cc / compiler-rt. */
static int fu_detect_ssse3(void) {
# if defined(_MSC_VER)
    int regs[4];
    __cpuid(regs, 1);
    return (regs[2] >> 9) & 1;
# else
    unsigned int eax, ebx, ecx, edx;
    if (!__get_cpuid(1, &eax, &ebx, &ecx, &edx)) return 0;
    return (ecx >> 9) & 1;
# endif
}
#endif

#if defined(__aarch64__) && !defined(FU_DISABLE_NEON)
# include <arm_neon.h>
# define FU_AARCH64 1
# if defined(__AARCH64EL__)
#  define FU_AARCH64_LE 1
# endif
#endif

ZEND_DECLARE_MODULE_GLOBALS(fast_uuid)

static zend_class_entry *fast_uuid_ce;
static zend_object_handlers fu_handlers;
static zend_class_entry *fu_ex_invalid_arg;
static zend_class_entry *fu_ex_invalid_str;
static zend_class_entry *fu_ex_unsupported;
static zend_string *fu_str_getcore;

static zend_always_inline void fu_store_be64(unsigned char *dst, uint64_t v) {
#if defined(WORDS_BIGENDIAN)
    memcpy(dst, &v, 8);
#elif defined(_MSC_VER)
    uint64_t be = _byteswap_uint64(v);
    memcpy(dst, &be, 8);
#else
    uint64_t be = __builtin_bswap64(v);
    memcpy(dst, &be, 8);
#endif
}

static zend_always_inline uint64_t fu_bswap64(uint64_t v) {
#if defined(WORDS_BIGENDIAN)
    return v;
#elif defined(_MSC_VER)
    return _byteswap_uint64(v);
#else
    return __builtin_bswap64(v);
#endif
}

#define FU_MAX_BATCH_COUNT 100000U
#define FU_MAX_RANDOM_BYTES (16U * 1024U * 1024U)
#define FU_MAX_NAME_BYTES (16U * 1024U * 1024U)  /* v3/v5 name length cap (DoS bound) */
#define FU_GREG_UNIX_EPOCH 0x01B21DD213814000ULL /* 1582-10-15 → 1970 in 100ns ticks */
#define FU_MS48_MAX 0xffffffffffffULL            /* 48-bit unix-ms field max */
#define FU_RANDB_MASK ((1ULL << 62) - 1)         /* v7 rand_b width */

/* byte->2hex lookup, built in MINIT */
static char fu_lut[512];
static int8_t fu_nib_lut[256];
static const char fu_hexd[] = "0123456789abcdef";

/* ------------------------------------------------------------------ */
/* object                                                             */
/* ------------------------------------------------------------------ */

typedef struct {
    unsigned char b[16];
    zend_string  *str;   /* lazily-built, cached canonical string */
    zend_object   std;   /* must be last */
} fu_obj;

static inline fu_obj *fu_from_zobj(zend_object *o) {
    return (fu_obj *)((char *)o - offsetof(fu_obj, std));
}

static zend_always_inline fu_obj *fu_alloc_obj(void) {
    fu_obj *u = (fu_obj *)emalloc(sizeof(fu_obj));
    zend_object_std_init(&u->std, fast_uuid_ce);
#if PHP_VERSION_ID < 80300
    u->std.handlers = &fu_handlers;
#endif
    u->str = NULL;
    return u;
}

static zend_object *fu_create(zend_class_entry *ce) {
    fu_obj *u;
    if (EXPECTED(ce == fast_uuid_ce)) {
        u = fu_alloc_obj();
    } else {
        u = zend_object_alloc(sizeof(fu_obj), ce);
        zend_object_std_init(&u->std, ce);
        u->std.handlers = &fu_handlers;
        u->str = NULL;
    }
    memset(u->b, 0, 16);
    return &u->std;
}

static void fu_free(zend_object *o) {
    fu_obj *u = fu_from_zobj(o);
    if (u->str) zend_string_release(u->str);
    zend_object_std_dtor(o);
}

static zend_object *fu_clone(zend_object *o) {
    fu_obj *src = fu_from_zobj(o);
    fu_obj *dst = fu_alloc_obj();
    memcpy(dst->b, src->b, 16);
    if (src->str) dst->str = zend_string_copy(src->str);
    return &dst->std;
}

static int fu_compare(zval *a, zval *b) {
    if (Z_TYPE_P(a) != IS_OBJECT || Z_TYPE_P(b) != IS_OBJECT ||
        Z_OBJCE_P(a) != fast_uuid_ce ||
        Z_OBJCE_P(b) != fast_uuid_ce) {
        return ZEND_UNCOMPARABLE;
    }
    int r = memcmp(fu_from_zobj(Z_OBJ_P(a))->b, fu_from_zobj(Z_OBJ_P(b))->b, 16);
    return r < 0 ? -1 : (r > 0 ? 1 : 0);
}

/* ------------------------------------------------------------------ */
/* formatting / parsing                                               */
/* ------------------------------------------------------------------ */

static inline void fu_hex32_scalar(const unsigned char *b, char *o) {
    const char *L = fu_lut;
    for (int i = 0; i < 16; i++) { o[i*2] = L[b[i]*2]; o[i*2+1] = L[b[i]*2+1]; }
}

static inline void fu_format36_scalar(const unsigned char *b, char *o) {
    const char *L = fu_lut;
    for (int i = 0; i < 4; i++)  { o[i*2]      = L[b[i]*2]; o[i*2+1]      = L[b[i]*2+1]; }
    o[8] = '-';
    for (int i = 4; i < 6; i++)  { o[i*2+1]    = L[b[i]*2]; o[i*2+2]      = L[b[i]*2+1]; }
    o[13] = '-';
    for (int i = 6; i < 8; i++)  { o[i*2+2]    = L[b[i]*2]; o[i*2+3]      = L[b[i]*2+1]; }
    o[18] = '-';
    for (int i = 8; i < 10; i++) { o[i*2+3]    = L[b[i]*2]; o[i*2+4]      = L[b[i]*2+1]; }
    o[23] = '-';
    for (int i = 10; i < 16; i++){ o[i*2+4]    = L[b[i]*2]; o[i*2+5]      = L[b[i]*2+1]; }
}

static inline void fu_format36_v4_scalar(const unsigned char *raw, char *o) {
    const char *L = fu_lut;
    unsigned char b6 = (raw[6] & 0x0f) | 0x40;
    unsigned char b8 = (raw[8] & 0x3f) | 0x80;
    for (int i = 0; i < 4; i++)  { o[i*2]      = L[raw[i]*2]; o[i*2+1]      = L[raw[i]*2+1]; }
    o[8] = '-';
    for (int i = 4; i < 6; i++)  { o[i*2+1]    = L[raw[i]*2]; o[i*2+2]      = L[raw[i]*2+1]; }
    o[13] = '-';
    o[14] = L[b6*2];     o[15] = L[b6*2+1];
    o[16] = L[raw[7]*2]; o[17] = L[raw[7]*2+1];
    o[18] = '-';
    o[19] = L[b8*2];     o[20] = L[b8*2+1];
    o[21] = L[raw[9]*2]; o[22] = L[raw[9]*2+1];
    o[23] = '-';
    for (int i = 10; i < 16; i++){ o[i*2+4]    = L[raw[i]*2]; o[i*2+5]      = L[raw[i]*2+1]; }
}

/* Format trailing 8 bytes b8[0..7] into o[19..35] as "xxxx-xxxxxxxxxxxx". */
static inline void fu_format_v7_tail17_scalar(const unsigned char *b8, char *o) {
    const char *L = fu_lut;
    o[19] = L[b8[0]*2]; o[20] = L[b8[0]*2+1];
    o[21] = L[b8[1]*2]; o[22] = L[b8[1]*2+1];
    o[23] = '-';
    for (int i = 2; i < 8; i++) {
        o[20 + i*2]     = L[b8[i]*2];
        o[20 + i*2 + 1] = L[b8[i]*2+1];
    }
}

#ifdef FU_X86
# if defined(__SSSE3__)
#  define FU_SSSE3_FN static zend_always_inline
# else
#  define FU_SSSE3_FN FU_TARGET_SSSE3 static
# endif

/* A single UUID fits one XMM register; AVX2 offers no benefit here. */
FU_SSSE3_FN void fu_hex32_ssse3(const unsigned char *b, char *o) {
    const __m128i v    = _mm_loadu_si128((const __m128i *)b);
    const __m128i mask = _mm_set1_epi8(0x0f);
    const __m128i lut  = _mm_setr_epi8('0','1','2','3','4','5','6','7',
                                       '8','9','a','b','c','d','e','f');
    __m128i hi = _mm_and_si128(_mm_srli_epi16(v, 4), mask);
    __m128i lo = _mm_and_si128(v, mask);
    __m128i hh = _mm_shuffle_epi8(lut, hi);
    __m128i lh = _mm_shuffle_epi8(lut, lo);
    _mm_storeu_si128((__m128i *)o,        _mm_unpacklo_epi8(hh, lh));
    _mm_storeu_si128((__m128i *)(o + 16), _mm_unpackhi_epi8(hh, lh));
}

FU_SSSE3_FN void fu_format36_ssse3_vec(__m128i v, char *o) {
    const __m128i mask  = _mm_set1_epi8(0x0f);
    const __m128i lut   = _mm_setr_epi8('0','1','2','3','4','5','6','7',
                                        '8','9','a','b','c','d','e','f');
    const __m128i shuf0 = _mm_setr_epi8(0,1,2,3,4,5,6,7, -1, 8,9,10,11, -1, 12,13);
    const __m128i dash0 = _mm_setr_epi8(0,0,0,0,0,0,0,0, '-', 0,0,0,0, '-', 0,0);
    const __m128i shuf1 = _mm_setr_epi8(0,1, -1, 2,3,4,5, -1, 6,7,8,9,10,11,12,13);
    const __m128i dash1 = _mm_setr_epi8(0,0, '-', 0,0,0,0, '-', 0,0,0,0,0,0,0,0);
    __m128i hi   = _mm_and_si128(_mm_srli_epi16(v, 4), mask);
    __m128i lo   = _mm_and_si128(v, mask);
    __m128i hh   = _mm_shuffle_epi8(lut, hi);
    __m128i lh   = _mm_shuffle_epi8(lut, lo);
    __m128i lo16 = _mm_unpacklo_epi8(hh, lh);
    __m128i hi16 = _mm_unpackhi_epi8(hh, lh);
    __m128i out0 = _mm_or_si128(_mm_shuffle_epi8(lo16, shuf0), dash0);
    __m128i mid  = _mm_alignr_epi8(hi16, lo16, 14);
    __m128i out1 = _mm_or_si128(_mm_shuffle_epi8(mid, shuf1), dash1);
    _mm_storeu_si128((__m128i *)o, out0);
    _mm_storeu_si128((__m128i *)(o + 16), out1);
    uint32_t tail = (uint32_t)_mm_cvtsi128_si32(_mm_srli_si128(hi16, 12));
    memcpy(o + 32, &tail, 4);
}

FU_SSSE3_FN void fu_format36_ssse3(const unsigned char *b, char *o) {
    fu_format36_ssse3_vec(_mm_loadu_si128((const __m128i *)b), o);
}

FU_SSSE3_FN void fu_format36_v4_ssse3(const unsigned char *raw, char *o) {
    __m128i v = _mm_loadu_si128((const __m128i *)raw);
    const __m128i clr = _mm_setr_epi8(-1,-1,-1,-1,-1,-1, 0x0f,-1, 0x3f,-1,-1,-1,-1,-1,-1,-1);
    const __m128i set = _mm_setr_epi8( 0, 0, 0, 0, 0, 0, 0x40, 0, (char)0x80, 0, 0, 0, 0, 0, 0, 0);
    v = _mm_or_si128(_mm_and_si128(v, clr), set);
    fu_format36_ssse3_vec(v, o);
}

FU_SSSE3_FN void fu_format_v7_tail17_ssse3(const unsigned char *b8, char *o) {
    __m128i v    = _mm_loadl_epi64((const __m128i *)b8);
    const __m128i mask = _mm_set1_epi8(0x0f);
    const __m128i lut  = _mm_setr_epi8('0','1','2','3','4','5','6','7',
                                       '8','9','a','b','c','d','e','f');
    __m128i hi   = _mm_and_si128(_mm_srli_epi16(v, 4), mask);
    __m128i lo   = _mm_and_si128(v, mask);
    __m128i hex  = _mm_unpacklo_epi8(_mm_shuffle_epi8(lut, hi), _mm_shuffle_epi8(lut, lo));
    _mm_storeu_si128((__m128i *)(o + 20), hex);
    uint32_t head4 = (uint32_t)_mm_cvtsi128_si32(hex);
    memcpy(o + 19, &head4, 4);
    o[23] = '-';
}

/* Validate 32 hex chars in v0/v1 and optionally decode into out[16]. */
FU_SSSE3_FN int fu_hex32_decode_ssse3_vecs(__m128i v0, __m128i v1, unsigned char *out) {
    const __m128i zero  = _mm_setzero_si128();
    const __m128i c0    = _mm_set1_epi8('0');
    const __m128i c9    = _mm_set1_epi8(9);
    const __m128i lc    = _mm_set1_epi8(0x20);
    const __m128i ca    = _mm_set1_epi8('a');
    const __m128i c5    = _mm_set1_epi8(5);
    __m128i d0    = _mm_sub_epi8(v0, c0);
    __m128i d1    = _mm_sub_epi8(v1, c0);
    __m128i is_d0 = _mm_cmpeq_epi8(_mm_subs_epu8(d0, c9), zero);
    __m128i is_d1 = _mm_cmpeq_epi8(_mm_subs_epu8(d1, c9), zero);
    __m128i a0    = _mm_sub_epi8(_mm_or_si128(v0, lc), ca);
    __m128i a1    = _mm_sub_epi8(_mm_or_si128(v1, lc), ca);
    __m128i is_a0 = _mm_cmpeq_epi8(_mm_subs_epu8(a0, c5), zero);
    __m128i is_a1 = _mm_cmpeq_epi8(_mm_subs_epu8(a1, c5), zero);
    __m128i ok    = _mm_and_si128(_mm_or_si128(is_d0, is_a0), _mm_or_si128(is_d1, is_a1));
    if (UNEXPECTED(_mm_movemask_epi8(ok) != 0xffff)) return 0;
    if (out) {
        const __m128i low4 = _mm_set1_epi8(0x0f);
        const __m128i mult = _mm_set1_epi16(0x0110);
        __m128i n0 = _mm_add_epi8(_mm_and_si128(v0, low4), _mm_and_si128(is_a0, c9));
        __m128i n1 = _mm_add_epi8(_mm_and_si128(v1, low4), _mm_and_si128(is_a1, c9));
        __m128i p0 = _mm_maddubs_epi16(n0, mult);
        __m128i p1 = _mm_maddubs_epi16(n1, mult);
        _mm_storeu_si128((__m128i *)out, _mm_packus_epi16(p0, p1));
    }
    return 1;
}

FU_SSSE3_FN int fu_parse36_ssse3(const char *s, unsigned char *out) {
    __m128i c0 = _mm_loadu_si128((const __m128i *)s);
    __m128i c1 = _mm_loadu_si128((const __m128i *)(s + 16));
    __m128i c2 = _mm_loadu_si128((const __m128i *)(s + 20));
    const __m128i p0m = _mm_setr_epi8(0,1,2,3,4,5,6,7, 9,10,11,12, 14,15, -1,-1);
    const __m128i p1m = _mm_setr_epi8(-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1, 0,1);
    const __m128i q0m = _mm_setr_epi8(3,4,5,6, -1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1);
    const __m128i q1m = _mm_setr_epi8(-1,-1,-1,-1, 4,5,6,7,8,9,10,11,12,13,14,15);
    __m128i v0 = _mm_or_si128(_mm_shuffle_epi8(c0, p0m), _mm_shuffle_epi8(c1, p1m));
    __m128i v1 = _mm_or_si128(_mm_shuffle_epi8(c1, q0m), _mm_shuffle_epi8(c2, q1m));
    return fu_hex32_decode_ssse3_vecs(v0, v1, out);
}

FU_SSSE3_FN int fu_parse32_ssse3(const char *s, unsigned char *out) {
    __m128i v0 = _mm_loadu_si128((const __m128i *)s);
    __m128i v1 = _mm_loadu_si128((const __m128i *)(s + 16));
    return fu_hex32_decode_ssse3_vecs(v0, v1, out);
}

FU_SSSE3_FN int fu_valid36_ssse3(const char *s) {
    return fu_parse36_ssse3(s, NULL);
}

FU_SSSE3_FN int fu_valid32_ssse3(const char *s) {
    return fu_parse32_ssse3(s, NULL);
}
#endif

#ifdef FU_AARCH64
static inline void fu_hex32_neon(const unsigned char *b, char *o) {
    const uint8x16_t v    = vld1q_u8((const uint8_t *)b);
    const uint8x16_t mask = vdupq_n_u8(0x0f);
    const uint8x16_t lut  = vld1q_u8((const uint8_t *)fu_hexd);
    const uint8x16_t hi   = vshrq_n_u8(v, 4);
    const uint8x16_t lo   = vandq_u8(v, mask);
    const uint8x16_t hh   = vqtbl1q_u8(lut, hi);
    const uint8x16_t lh   = vqtbl1q_u8(lut, lo);
    const uint8x16x2_t z  = vzipq_u8(hh, lh);
    vst1q_u8((uint8_t *)o,       z.val[0]);
    vst1q_u8((uint8_t *)(o + 16), z.val[1]);
}

# ifdef FU_AARCH64_LE
static inline void fu_format36_neon_vec(uint8x16_t v, char *o) {
    static const uint8_t shuf0_d[16] = { 0,1,2,3,4,5,6,7, 0x80, 8,9,10,11, 0x80, 12,13 };
    static const uint8_t dash0_d[16] = { 0,0,0,0,0,0,0,0, '-',  0,0,0,0,   '-',  0,0 };
    static const uint8_t shuf1_d[16] = { 0,1, 0x80, 2,3,4,5, 0x80, 6,7,8,9,10,11,12,13 };
    static const uint8_t dash1_d[16] = { 0,0, '-',  0,0,0,0, '-',  0,0,0,0,0,0,0,0 };
    const uint8x16_t mask = vdupq_n_u8(0x0f);
    const uint8x16_t lut  = vld1q_u8((const uint8_t *)fu_hexd);
    const uint8x16_t hh   = vqtbl1q_u8(lut, vshrq_n_u8(v, 4));
    const uint8x16_t lh   = vqtbl1q_u8(lut, vandq_u8(v, mask));
    const uint8x16x2_t z  = vzipq_u8(hh, lh);
    const uint8x16_t lo16 = z.val[0];
    const uint8x16_t hi16 = z.val[1];
    const uint8x16_t out0 = vorrq_u8(vqtbl1q_u8(lo16, vld1q_u8(shuf0_d)), vld1q_u8(dash0_d));
    const uint8x16_t mid  = vextq_u8(lo16, hi16, 14);
    const uint8x16_t out1 = vorrq_u8(vqtbl1q_u8(mid, vld1q_u8(shuf1_d)), vld1q_u8(dash1_d));
    vst1q_u8((uint8_t *)o, out0);
    vst1q_u8((uint8_t *)(o + 16), out1);
    uint32_t tail = vgetq_lane_u32(vreinterpretq_u32_u8(hi16), 3);
    memcpy(o + 32, &tail, 4);
}

static inline void fu_format36_neon(const unsigned char *b, char *o) {
    fu_format36_neon_vec(vld1q_u8((const uint8_t *)b), o);
}

static inline void fu_format36_v4_neon(const unsigned char *raw, char *o) {
    static const uint8_t clr_d[16] = {
        0xff,0xff,0xff,0xff,0xff,0xff,0x0f,0xff,0x3f,0xff,0xff,0xff,0xff,0xff,0xff,0xff
    };
    static const uint8_t set_d[16] = {
        0,0,0,0,0,0,0x40,0,0x80,0,0,0,0,0,0,0
    };
    uint8x16_t v = vld1q_u8((const uint8_t *)raw);
    v = vorrq_u8(vandq_u8(v, vld1q_u8(clr_d)), vld1q_u8(set_d));
    fu_format36_neon_vec(v, o);
}

static inline void fu_format_v7_tail17_neon(const unsigned char *b8, char *o) {
    uint8x8_t v    = vld1_u8((const uint8_t *)b8);
    uint8x16_t lut = vld1q_u8((const uint8_t *)fu_hexd);
    uint8x8_t hh   = vqtbl1_u8(lut, vshr_n_u8(v, 4));
    uint8x8_t lh   = vqtbl1_u8(lut, vand_u8(v, vdup_n_u8(0x0f)));
    uint8x8x2_t z  = vzip_u8(hh, lh);
    uint8x16_t hex = vcombine_u8(z.val[0], z.val[1]);
    vst1q_u8((uint8_t *)(o + 20), hex);
    uint32_t head4 = vgetq_lane_u32(vreinterpretq_u32_u8(hex), 0);
    memcpy(o + 19, &head4, 4);
    o[23] = '-';
}

static inline int fu_hex32_decode_neon_vecs(uint8x16_t v0, uint8x16_t v1, unsigned char *out) {
    const uint8x16_t c0   = vdupq_n_u8('0');
    const uint8x16_t c9   = vdupq_n_u8(9);
    const uint8x16_t lc   = vdupq_n_u8(0x20);
    const uint8x16_t ca   = vdupq_n_u8('a');
    const uint8x16_t c5   = vdupq_n_u8(5);
    uint8x16_t is_d0 = vcleq_u8(vsubq_u8(v0, c0), c9);
    uint8x16_t is_d1 = vcleq_u8(vsubq_u8(v1, c0), c9);
    uint8x16_t is_a0 = vcleq_u8(vsubq_u8(vorrq_u8(v0, lc), ca), c5);
    uint8x16_t is_a1 = vcleq_u8(vsubq_u8(vorrq_u8(v1, lc), ca), c5);
    uint8x16_t ok    = vandq_u8(vorrq_u8(is_d0, is_a0), vorrq_u8(is_d1, is_a1));
    if (UNEXPECTED(vminvq_u8(ok) == 0)) return 0;
    if (out) {
        const uint8x16_t low4 = vdupq_n_u8(0x0f);
        uint8x16_t n0 = vaddq_u8(vandq_u8(v0, low4), vandq_u8(is_a0, c9));
        uint8x16_t n1 = vaddq_u8(vandq_u8(v1, low4), vandq_u8(is_a1, c9));
        uint8x16_t hi = vuzp1q_u8(n0, n1);
        uint8x16_t lo = vuzp2q_u8(n0, n1);
        vst1q_u8((uint8_t *)out, vsliq_n_u8(lo, hi, 4));
    }
    return 1;
}

static inline int fu_parse36_neon(const char *s, unsigned char *out) {
    static const uint8_t idx0_d[16] = { 0,1,2,3,4,5,6,7, 9,10,11,12, 14,15, 16,17 };
    static const uint8_t idx1_d[16] = { 3,4,5,6, 20,21,22,23,24,25,26,27,28,29,30,31 };
    uint8x16_t c0 = vld1q_u8((const uint8_t *)s);
    uint8x16_t c1 = vld1q_u8((const uint8_t *)(s + 16));
    uint8x16_t c2 = vld1q_u8((const uint8_t *)(s + 20));
    uint8x16x2_t pair0 = { { c0, c1 } };
    uint8x16x2_t pair1 = { { c1, c2 } };
    uint8x16_t v0 = vqtbl2q_u8(pair0, vld1q_u8(idx0_d));
    uint8x16_t v1 = vqtbl2q_u8(pair1, vld1q_u8(idx1_d));
    return fu_hex32_decode_neon_vecs(v0, v1, out);
}

static inline int fu_parse32_neon(const char *s, unsigned char *out) {
    return fu_hex32_decode_neon_vecs(
        vld1q_u8((const uint8_t *)s),
        vld1q_u8((const uint8_t *)(s + 16)),
        out);
}
# endif
#endif

static inline void fu_hex32(const unsigned char *b, char *o) {
#ifdef FU_X86
# if defined(__SSSE3__)
    fu_hex32_ssse3(b, o);
    return;
# else
    if (EXPECTED(fu_has_ssse3)) { fu_hex32_ssse3(b, o); return; }
# endif
#endif
#ifdef FU_AARCH64
    fu_hex32_neon(b, o);
    return;
#endif
    fu_hex32_scalar(b, o);
}

/* canonical 8-4-4-4-12 into 36 bytes (no NUL) */
static inline void fu_format36(const unsigned char *b, char *o) {
#ifdef FU_X86
# if defined(__SSSE3__)
    fu_format36_ssse3(b, o);
    return;
# else
    if (EXPECTED(fu_has_ssse3)) { fu_format36_ssse3(b, o); return; }
# endif
#endif
#ifdef FU_AARCH64_LE
    fu_format36_neon(b, o);
    return;
#endif
    fu_format36_scalar(b, o);
}

static inline void fu_format36_v4(const unsigned char *raw, char *o) {
#ifdef FU_X86
# if defined(__SSSE3__)
    fu_format36_v4_ssse3(raw, o);
    return;
# else
    if (EXPECTED(fu_has_ssse3)) { fu_format36_v4_ssse3(raw, o); return; }
# endif
#endif
#ifdef FU_AARCH64_LE
    fu_format36_v4_neon(raw, o);
    return;
#endif
    fu_format36_v4_scalar(raw, o);
}

static inline void fu_format_v7_tail17(const unsigned char *b8, char *o) {
#ifdef FU_X86
# if defined(__SSSE3__)
    fu_format_v7_tail17_ssse3(b8, o);
    return;
# else
    if (EXPECTED(fu_has_ssse3)) { fu_format_v7_tail17_ssse3(b8, o); return; }
# endif
#endif
#ifdef FU_AARCH64_LE
    fu_format_v7_tail17_neon(b8, o);
    return;
#endif
    fu_format_v7_tail17_scalar(b8, o);
}

static zend_always_inline bool fu_has_urn_prefix(const char *s, size_t len) {
    return len >= 4
        && (s[0] | 0x20) == 'u'
        && (s[1] | 0x20) == 'r'
        && (s[2] | 0x20) == 'n'
        && s[3] == ':';
}

static zend_always_inline bool fu_has_uuid_prefix(const char *s, size_t len) {
    return len >= 5
        && (s[0] | 0x20) == 'u'
        && (s[1] | 0x20) == 'u'
        && (s[2] | 0x20) == 'i'
        && (s[3] | 0x20) == 'd'
        && s[4] == ':';
}

/* Tolerant wrapper stripping: optional "urn:" then "uuid:" (once total) and {} up to depth 2. */
static zend_never_inline void fu_strip_wrappers(const char **sp, size_t *lenp) {
    const char *s = *sp;
    size_t len = *lenp;
    bool prefixed = false;
    for (int pass = 0; pass < 2; pass++) {
        size_t before = len;
        if (!prefixed) {
            if (fu_has_urn_prefix(s, len)) {
                s += 4; len -= 4; prefixed = true;
            }
            if (fu_has_uuid_prefix(s, len)) {
                s += 5; len -= 5; prefixed = true;
            }
        }
        if (len >= 2 && s[0] == '{' && s[len - 1] == '}') {
            s++; len -= 2;
        }
        if (len == before) break;
    }
    *sp = s;
    *lenp = len;
}

static zend_never_inline bool fu_strip_strict_wrappers(const char **sp, size_t *lenp) {
    const char *s = *sp;
    size_t len = *lenp;
    if (len < 36 || len > 47) return false;
    if (fu_has_urn_prefix(s, len)) { s += 4; len -= 4; }
    if (fu_has_uuid_prefix(s, len)) { s += 5; len -= 5; }
    if (len == 38 && s[0] == '{' && s[37] == '}') { s++; len -= 2; }
    if (len != 36) return false;
    *sp = s;
    *lenp = len;
    return true;
}

static zend_always_inline bool fu_hyphens_ok(const char *s) {
    return (((unsigned char)(s[8] ^ '-')
           | (unsigned char)(s[13] ^ '-')
           | (unsigned char)(s[18] ^ '-')
           | (unsigned char)(s[23] ^ '-')) == 0);
}

#define FU_PARSE_HEX_ACC(pos, outidx) do { \
    int hi_ = fu_nib_lut[(unsigned char)s[(pos)]]; \
    int lo_ = fu_nib_lut[(unsigned char)s[(pos) + 1]]; \
    bad |= hi_ | lo_; \
    out[(outidx)] = (unsigned char)(((unsigned int)hi_ << 4) | (unsigned int)lo_); \
} while (0)

static inline int fu_parse36_scalar(const char *s, unsigned char out[16]) {
    int bad = 0;
    FU_PARSE_HEX_ACC(0, 0);   FU_PARSE_HEX_ACC(2, 1);
    FU_PARSE_HEX_ACC(4, 2);   FU_PARSE_HEX_ACC(6, 3);
    FU_PARSE_HEX_ACC(9, 4);   FU_PARSE_HEX_ACC(11, 5);
    FU_PARSE_HEX_ACC(14, 6);  FU_PARSE_HEX_ACC(16, 7);
    FU_PARSE_HEX_ACC(19, 8);  FU_PARSE_HEX_ACC(21, 9);
    FU_PARSE_HEX_ACC(24, 10); FU_PARSE_HEX_ACC(26, 11);
    FU_PARSE_HEX_ACC(28, 12); FU_PARSE_HEX_ACC(30, 13);
    FU_PARSE_HEX_ACC(32, 14); FU_PARSE_HEX_ACC(34, 15);
    return bad >= 0;
}

static inline int fu_parse32_scalar(const char *s, unsigned char out[16]) {
    int bad = 0;
    for (int i = 0; i < 16; i++) {
        FU_PARSE_HEX_ACC(i * 2, i);
    }
    return bad >= 0;
}

#undef FU_PARSE_HEX_ACC

static inline bool fu_valid36_scalar(const char *s) {
    static const uint8_t pos[32] = {
        0,1,2,3,4,5,6,7, 9,10,11,12, 14,15,16,17, 19,20,21,22,
        24,25,26,27,28,29,30,31,32,33,34,35
    };
    int bad = 0;
    for (int i = 0; i < 32; i++) bad |= fu_nib_lut[(unsigned char)s[pos[i]]];
    return bad >= 0;
}

static inline bool fu_valid32_scalar(const char *s) {
    int bad = 0;
    for (int i = 0; i < 32; i++) bad |= fu_nib_lut[(unsigned char)s[i]];
    return bad >= 0;
}

static inline int fu_parse_hex32(const char *s, unsigned char out[16]) {
#ifdef FU_X86
# if defined(__SSSE3__)
    return fu_parse32_ssse3(s, out);
# else
    if (EXPECTED(fu_has_ssse3)) return fu_parse32_ssse3(s, out);
# endif
#endif
#ifdef FU_AARCH64_LE
    return fu_parse32_neon(s, out);
#endif
    return fu_parse32_scalar(s, out);
}

static inline int fu_parse_canon36(const char *s, unsigned char out[16]) {
    if (UNEXPECTED(!fu_hyphens_ok(s))) return 0;
#ifdef FU_X86
# if defined(__SSSE3__)
    return fu_parse36_ssse3(s, out);
# else
    if (EXPECTED(fu_has_ssse3)) return fu_parse36_ssse3(s, out);
# endif
#endif
#ifdef FU_AARCH64_LE
    return fu_parse36_neon(s, out);
#endif
    return fu_parse36_scalar(s, out);
}

static inline bool fu_valid_canon36(const char *s) {
    if (UNEXPECTED(!fu_hyphens_ok(s))) return false;
#ifdef FU_X86
# if defined(__SSSE3__)
    return fu_valid36_ssse3(s) != 0;
# else
    if (EXPECTED(fu_has_ssse3)) return fu_valid36_ssse3(s) != 0;
# endif
#endif
#ifdef FU_AARCH64_LE
    return fu_parse36_neon(s, NULL) != 0;
#endif
    return fu_valid36_scalar(s);
}

static inline bool fu_valid_hex32(const char *s) {
#ifdef FU_X86
# if defined(__SSSE3__)
    return fu_valid32_ssse3(s) != 0;
# else
    if (EXPECTED(fu_has_ssse3)) return fu_valid32_ssse3(s) != 0;
# endif
#endif
#ifdef FU_AARCH64_LE
    return fu_parse32_neon(s, NULL) != 0;
#endif
    return fu_valid32_scalar(s);
}

/* tolerant parser: accepts canonical 36, bare 32-hex, an optional "urn:" then an optional "uuid:" prefix
   (case-insensitive), and {} wrappers in any composition up to depth 2 ({urn:uuid:...}, urn:uuid:{...}) */
static int fu_parse(const char *s, size_t len, unsigned char out[16]) {
    if (UNEXPECTED(len == 0)) return 0;
    if (UNEXPECTED(s[0] == '{' || (s[0] | 0x20) == 'u')) {
        fu_strip_wrappers(&s, &len);
    }
    if (EXPECTED(len == 36)) return fu_parse_canon36(s, out);
    if (len == 32) return fu_parse_hex32(s, out);
    return 0;
}

static bool fu_valid_tolerant(const char *s, size_t len) {
    if (UNEXPECTED(len == 0)) return false;
    if (UNEXPECTED(s[0] == '{' || (s[0] | 0x20) == 'u')) {
        fu_strip_wrappers(&s, &len);
    }
    if (EXPECTED(len == 36)) return fu_valid_canon36(s);
    if (len == 32) return fu_valid_hex32(s);
    return false;
}

/* ramsey/uuid GenericValidator grammar: an optional "urn:", then an optional "uuid:" (case-insensitive), then
   the 8-4-4-4-12 form, optionally inside one pair of braces. No bare 32-hex and no nested wrappers. */
static bool fu_valid_strict(const char *s, size_t len) {
    if (UNEXPECTED(len != 36)) {
        if (!fu_strip_strict_wrappers(&s, &len)) return false;
    }
    return fu_valid_canon36(s);
}

static zend_string *fu_str(fu_obj *u) {
    if (u->str) return zend_string_copy(u->str);
    zend_string *s = zend_string_alloc(36, 0);
    fu_format36(u->b, ZSTR_VAL(s));
    ZSTR_VAL(s)[36] = '\0';
    u->str = s;
    return zend_string_copy(s);
}

static zend_result fu_cast(zend_object *o, zval *ret, int type) {
    if (type == IS_STRING) { ZVAL_STR(ret, fu_str(fu_from_zobj(o))); return SUCCESS; }
    return zend_std_cast_object_tostring(o, ret, type);
}

/* State lives outside the property table. Expose "uuid" for debugging and
   var_export()/__set_state() round-trips. */
static HashTable *fu_get_debug_info(zend_object *o, int *is_temp) {
    zval zv;
    HashTable *ht = zend_new_array(1);
    ZVAL_STR(&zv, fu_str(fu_from_zobj(o)));
    zend_hash_str_update(ht, "uuid", sizeof("uuid") - 1, &zv);
    *is_temp = 1;
    return ht;
}

static zend_array *fu_get_properties_for(zend_object *o, zend_prop_purpose purpose) {
    if (purpose == ZEND_PROP_PURPOSE_VAR_EXPORT) {
        zval zv;
        zend_array *ht = zend_new_array(1);
        ZVAL_STR(&zv, fu_str(fu_from_zobj(o)));
        zend_hash_str_update(ht, "uuid", sizeof("uuid") - 1, &zv);
        return ht;
    }
    return zend_std_get_properties_for(o, purpose);
}

/* ------------------------------------------------------------------ */
/* randomness                                                         */
/* ------------------------------------------------------------------ */

/* Userspace CSPRNG: AES-256-CTR with fast key erasure (Bernstein, 2017).
   Every generate call emits at most FU_DRBG_CHUNK bytes, then overwrites the
   key with the next two keystream blocks, so the key cannot regenerate output
   of earlier refills. fu_rand() does not zero bytes as it serves them (that
   cost ~3.5% on uuid_v4): a read of the module state recovers the
   already-served part of the current refill (up to 8 KiB), the unserved rest,
   and everything the key yields until the next reseed. Fresh OS entropy is
   XORed into key and counter every FU_DRBG_RESEED_BYTES of output, which also
   bounds how much output two clones of one VM snapshot can share. Only
   hardware AES is used: a table-driven software AES leaks key bits through
   cache timing, so CPUs without AES instructions keep refilling from
   the OS CSPRNG. */
#define FU_DRBG_CHUNK        sizeof(FAST_UUID_G(rbuf))
#define FU_DRBG_RESEED_BYTES (64U * 1024U)

enum {
    /* No backend for this architecture/compiler, or built with -DFU_DISABLE_AES,
       -DFU_DISABLE_SSSE3 (x86) or -DFU_DISABLE_NEON (AArch64): the AES backends
       sit behind the same FU_X86 / FU_AARCH64 gates as the hex formatters. */
    FU_AES_NOT_BUILT = 0,
    FU_AES_NO_CPU,         /* backend built, CPU lacks the AES instructions */
    FU_AES_NO_DETECT,      /* backend built, no runtime AES probe on this OS */
    FU_AES_SELFTEST_FAILED,
    FU_AES_ACTIVE
};
static int fu_aes_state = FU_AES_NOT_BUILT;  /* set once in MINIT */

#if defined(FU_X86) && !defined(FU_DISABLE_AES)
# define FU_AES_X86 1
# define FU_AES_NAME "AES-NI"
# if defined(_MSC_VER)
#  define FU_TARGET_AES
# else
#  define FU_TARGET_AES __attribute__((target("aes,sse2")))
# endif
#elif defined(FU_AARCH64) && defined(__AARCH64EL__) && !defined(FU_DISABLE_AES)
/* Clang's arm_neon.h hides the crypto intrinsics unless AES is part of the
   compile-time baseline (Apple arm64 always is); GCC exposes them to a
   target("+crypto") function. */
# if defined(__ARM_FEATURE_AES) || defined(__ARM_FEATURE_CRYPTO)
#  define FU_AES_ARM 1
#  define FU_TARGET_AES
# elif defined(__GNUC__) && !defined(__clang__) && __GNUC__ >= 8
#  define FU_AES_ARM 1
#  define FU_TARGET_AES __attribute__((target("+crypto")))
# endif
# ifdef FU_AES_ARM
#  define FU_AES_NAME "ARMv8 Crypto Extensions"
#  if defined(__linux__)
#   include <sys/auxv.h>
#   ifndef HWCAP_AES
#    define HWCAP_AES (1UL << 3)
#   endif
#  endif
# endif
#endif

#ifdef FU_AES_X86
static int fu_detect_aes(void) {
# if defined(_MSC_VER)
    int regs[4];
    __cpuid(regs, 1);
    return (regs[2] >> 25) & 1;
# else
    unsigned int eax, ebx, ecx, edx;
    if (!__get_cpuid(1, &eax, &ebx, &ecx, &edx)) return 0;
    return (ecx >> 25) & 1;
# endif
}

/* AES-256 key schedule steps. Macros, not functions: aeskeygenassist takes its
   round constant as an immediate, which an -O0 build does not propagate
   through a function parameter. */
# define FU_AESNI_EXPAND_EVEN(t1, t3, rcon) do { \
    __m128i a_ = _mm_shuffle_epi32(_mm_aeskeygenassist_si128((t3), (rcon)), 0xff); \
    (t1) = _mm_xor_si128((t1), _mm_slli_si128((t1), 4)); \
    (t1) = _mm_xor_si128((t1), _mm_slli_si128((t1), 8)); \
    (t1) = _mm_xor_si128((t1), a_); \
} while (0)
# define FU_AESNI_EXPAND_ODD(t3, t1) do { \
    __m128i a_ = _mm_shuffle_epi32(_mm_aeskeygenassist_si128((t1), 0x00), 0xaa); \
    (t3) = _mm_xor_si128((t3), _mm_slli_si128((t3), 4)); \
    (t3) = _mm_xor_si128((t3), _mm_slli_si128((t3), 8)); \
    (t3) = _mm_xor_si128((t3), a_); \
} while (0)

FU_TARGET_AES static void fu_aes_expand(const unsigned char *key, __m128i *rk) {
    __m128i t1 = _mm_loadu_si128((const __m128i *)key);
    __m128i t3 = _mm_loadu_si128((const __m128i *)(key + 16));
    rk[0] = t1; rk[1] = t3;
    FU_AESNI_EXPAND_EVEN(t1, t3, 0x01); rk[2]  = t1; FU_AESNI_EXPAND_ODD(t3, t1); rk[3]  = t3;
    FU_AESNI_EXPAND_EVEN(t1, t3, 0x02); rk[4]  = t1; FU_AESNI_EXPAND_ODD(t3, t1); rk[5]  = t3;
    FU_AESNI_EXPAND_EVEN(t1, t3, 0x04); rk[6]  = t1; FU_AESNI_EXPAND_ODD(t3, t1); rk[7]  = t3;
    FU_AESNI_EXPAND_EVEN(t1, t3, 0x08); rk[8]  = t1; FU_AESNI_EXPAND_ODD(t3, t1); rk[9]  = t3;
    FU_AESNI_EXPAND_EVEN(t1, t3, 0x10); rk[10] = t1; FU_AESNI_EXPAND_ODD(t3, t1); rk[11] = t3;
    FU_AESNI_EXPAND_EVEN(t1, t3, 0x20); rk[12] = t1; FU_AESNI_EXPAND_ODD(t3, t1); rk[13] = t3;
    FU_AESNI_EXPAND_EVEN(t1, t3, 0x40); rk[14] = t1;
}
#endif /* FU_AES_X86 */

#if defined(FU_AES_X86) || defined(FU_AES_ARM)
/* One round step on the eight in-flight CTR blocks b0..b7. */
# define FU_AES_ROUNDS8(op, k) do { \
    b0 = op(b0, (k)); b1 = op(b1, (k)); b2 = op(b2, (k)); b3 = op(b3, (k)); \
    b4 = op(b4, (k)); b5 = op(b5, (k)); b6 = op(b6, (k)); b7 = op(b7, (k)); \
} while (0)
#endif

#ifdef FU_AES_X86
/* Writes len bytes of keystream under key to out, then replaces key with the
   next two keystream blocks. The counter advances in its low 64-bit lane
   only: uniqueness matters per key, and one key never covers more than
   FU_DRBG_CHUNK / 16 + 2 blocks. */
FU_TARGET_AES static void fu_aes_ctr(unsigned char *key, unsigned char *ctr, unsigned char *out, size_t len) {
    __m128i rk[15];
    const __m128i one = _mm_set_epi32(0, 0, 0, 1);
    __m128i c = _mm_loadu_si128((const __m128i *)ctr);
    fu_aes_expand(key, rk);

    for (; len >= 128; len -= 128, out += 128) {
        __m128i b0, b1, b2, b3, b4, b5, b6, b7;
        b0 = _mm_xor_si128(c, rk[0]); c = _mm_add_epi64(c, one);
        b1 = _mm_xor_si128(c, rk[0]); c = _mm_add_epi64(c, one);
        b2 = _mm_xor_si128(c, rk[0]); c = _mm_add_epi64(c, one);
        b3 = _mm_xor_si128(c, rk[0]); c = _mm_add_epi64(c, one);
        b4 = _mm_xor_si128(c, rk[0]); c = _mm_add_epi64(c, one);
        b5 = _mm_xor_si128(c, rk[0]); c = _mm_add_epi64(c, one);
        b6 = _mm_xor_si128(c, rk[0]); c = _mm_add_epi64(c, one);
        b7 = _mm_xor_si128(c, rk[0]); c = _mm_add_epi64(c, one);
        FU_AES_ROUNDS8(_mm_aesenc_si128, rk[1]);
        FU_AES_ROUNDS8(_mm_aesenc_si128, rk[2]);
        FU_AES_ROUNDS8(_mm_aesenc_si128, rk[3]);
        FU_AES_ROUNDS8(_mm_aesenc_si128, rk[4]);
        FU_AES_ROUNDS8(_mm_aesenc_si128, rk[5]);
        FU_AES_ROUNDS8(_mm_aesenc_si128, rk[6]);
        FU_AES_ROUNDS8(_mm_aesenc_si128, rk[7]);
        FU_AES_ROUNDS8(_mm_aesenc_si128, rk[8]);
        FU_AES_ROUNDS8(_mm_aesenc_si128, rk[9]);
        FU_AES_ROUNDS8(_mm_aesenc_si128, rk[10]);
        FU_AES_ROUNDS8(_mm_aesenc_si128, rk[11]);
        FU_AES_ROUNDS8(_mm_aesenc_si128, rk[12]);
        FU_AES_ROUNDS8(_mm_aesenc_si128, rk[13]);
        FU_AES_ROUNDS8(_mm_aesenclast_si128, rk[14]);
        _mm_storeu_si128((__m128i *)(out),       b0);
        _mm_storeu_si128((__m128i *)(out + 16),  b1);
        _mm_storeu_si128((__m128i *)(out + 32),  b2);
        _mm_storeu_si128((__m128i *)(out + 48),  b3);
        _mm_storeu_si128((__m128i *)(out + 64),  b4);
        _mm_storeu_si128((__m128i *)(out + 80),  b5);
        _mm_storeu_si128((__m128i *)(out + 96),  b6);
        _mm_storeu_si128((__m128i *)(out + 112), b7);
    }
    /* Remaining whole blocks, an optional partial block, then the two
       new-key blocks, two at a time. */
    while (1) {
        size_t take = len < 32 ? len : 32;
        int rekey = take == 0;
        unsigned char tmp[32];
        __m128i x0 = _mm_xor_si128(c, rk[0]); c = _mm_add_epi64(c, one);
        __m128i x1 = _mm_xor_si128(c, rk[0]);
        if (rekey || take > 16) c = _mm_add_epi64(c, one);
        for (int r = 1; r < 14; r++) { x0 = _mm_aesenc_si128(x0, rk[r]); x1 = _mm_aesenc_si128(x1, rk[r]); }
        x0 = _mm_aesenclast_si128(x0, rk[14]);
        x1 = _mm_aesenclast_si128(x1, rk[14]);
        if (rekey) {
            _mm_storeu_si128((__m128i *)key,        x0);
            _mm_storeu_si128((__m128i *)(key + 16), x1);
            break;
        }
        _mm_storeu_si128((__m128i *)tmp,        x0);
        _mm_storeu_si128((__m128i *)(tmp + 16), x1);
        memcpy(out, tmp, take);
        ZEND_SECURE_ZERO(tmp, sizeof(tmp));
        out += take; len -= take;
    }
    _mm_storeu_si128((__m128i *)ctr, c);
    ZEND_SECURE_ZERO(rk, sizeof(rk));
}
#endif /* FU_AES_X86 */

#ifdef FU_AES_ARM
/* 1 = present, 0 = absent, -1 = this OS has no probe wired up. */
static int fu_detect_aes(void) {
# if defined(__linux__)
    return (getauxval(AT_HWCAP) & HWCAP_AES) != 0;
# elif defined(__APPLE__)
    return 1;  /* every Apple arm64 core implements the crypto extensions */
# else
    return -1;
# endif
}

# define FU_AES_ARM_STEP_EVEN(t0, t1, rcon_val) do { \
    uint8x16_t s_  = vaeseq_u8(vqtbl1q_u8((t1), rot_idx), zero); \
    uint8x16_t rc_ = vreinterpretq_u8_u32(vdupq_n_u32((rcon_val))); \
    (t0) = veorq_u8((t0), vextq_u8(zero, (t0), 12)); \
    (t0) = veorq_u8((t0), vextq_u8(zero, (t0), 8)); \
    (t0) = veorq_u8((t0), veorq_u8(s_, rc_)); \
} while (0)

# define FU_AES_ARM_STEP_ODD(t1, t0) do { \
    uint8x16_t s_ = vaeseq_u8(vqtbl1q_u8((t0), sub_idx), zero); \
    (t1) = veorq_u8((t1), vextq_u8(zero, (t1), 12)); \
    (t1) = veorq_u8((t1), vextq_u8(zero, (t1), 8)); \
    (t1) = veorq_u8((t1), s_); \
} while (0)

/* Pure NEON FIPS-197 5.2 key expansion (Nk = 8, little-endian): all four
   columns of the AESE input hold the same word so ShiftRows is identity. */
FU_TARGET_AES static void fu_aes_expand(const unsigned char *key, uint8x16_t *rk) {
    static const uint8_t rot_idx_d[16] = {
        13,14,15,12, 13,14,15,12, 13,14,15,12, 13,14,15,12
    };
    static const uint8_t sub_idx_d[16] = {
        12,13,14,15, 12,13,14,15, 12,13,14,15, 12,13,14,15
    };
    const uint8x16_t zero    = vdupq_n_u8(0);
    const uint8x16_t rot_idx = vld1q_u8(rot_idx_d);
    const uint8x16_t sub_idx = vld1q_u8(sub_idx_d);
    uint8x16_t t0 = vld1q_u8(key);
    uint8x16_t t1 = vld1q_u8(key + 16);
    rk[0] = t0; rk[1] = t1;
    FU_AES_ARM_STEP_EVEN(t0, t1, 0x01); rk[2]  = t0; FU_AES_ARM_STEP_ODD(t1, t0); rk[3]  = t1;
    FU_AES_ARM_STEP_EVEN(t0, t1, 0x02); rk[4]  = t0; FU_AES_ARM_STEP_ODD(t1, t0); rk[5]  = t1;
    FU_AES_ARM_STEP_EVEN(t0, t1, 0x04); rk[6]  = t0; FU_AES_ARM_STEP_ODD(t1, t0); rk[7]  = t1;
    FU_AES_ARM_STEP_EVEN(t0, t1, 0x08); rk[8]  = t0; FU_AES_ARM_STEP_ODD(t1, t0); rk[9]  = t1;
    FU_AES_ARM_STEP_EVEN(t0, t1, 0x10); rk[10] = t0; FU_AES_ARM_STEP_ODD(t1, t0); rk[11] = t1;
    FU_AES_ARM_STEP_EVEN(t0, t1, 0x20); rk[12] = t0; FU_AES_ARM_STEP_ODD(t1, t0); rk[13] = t1;
    FU_AES_ARM_STEP_EVEN(t0, t1, 0x40); rk[14] = t0;
}

# undef FU_AES_ARM_STEP_EVEN
# undef FU_AES_ARM_STEP_ODD

# define FU_AESE_MC(s, k) vaesmcq_u8(vaeseq_u8((s), (k)))
# define FU_AESE_LAST(s, k) vaeseq_u8((s), (k))

/* Same contract as the AES-NI fu_aes_ctr. */
FU_TARGET_AES static void fu_aes_ctr(unsigned char *key, unsigned char *ctr, unsigned char *out, size_t len) {
    uint8x16_t rk[15];
    const uint64x2_t one = vcombine_u64(vcreate_u64(1), vcreate_u64(0));
    uint64x2_t c = vreinterpretq_u64_u8(vld1q_u8(ctr));
    fu_aes_expand(key, rk);
    const uint8x16_t rk0  = rk[0],  rk1  = rk[1],  rk2  = rk[2],  rk3  = rk[3],  rk4  = rk[4];
    const uint8x16_t rk5  = rk[5],  rk6  = rk[6],  rk7  = rk[7],  rk8  = rk[8],  rk9  = rk[9];
    const uint8x16_t rk10 = rk[10], rk11 = rk[11], rk12 = rk[12], rk13 = rk[13], rk14 = rk[14];

    for (; len >= 128; len -= 128, out += 128) {
        uint8x16_t b0, b1, b2, b3, b4, b5, b6, b7;
        b0 = vreinterpretq_u8_u64(c); c = vaddq_u64(c, one);
        b1 = vreinterpretq_u8_u64(c); c = vaddq_u64(c, one);
        b2 = vreinterpretq_u8_u64(c); c = vaddq_u64(c, one);
        b3 = vreinterpretq_u8_u64(c); c = vaddq_u64(c, one);
        b4 = vreinterpretq_u8_u64(c); c = vaddq_u64(c, one);
        b5 = vreinterpretq_u8_u64(c); c = vaddq_u64(c, one);
        b6 = vreinterpretq_u8_u64(c); c = vaddq_u64(c, one);
        b7 = vreinterpretq_u8_u64(c); c = vaddq_u64(c, one);
        FU_AES_ROUNDS8(FU_AESE_MC, rk0);
        FU_AES_ROUNDS8(FU_AESE_MC, rk1);
        FU_AES_ROUNDS8(FU_AESE_MC, rk2);
        FU_AES_ROUNDS8(FU_AESE_MC, rk3);
        FU_AES_ROUNDS8(FU_AESE_MC, rk4);
        FU_AES_ROUNDS8(FU_AESE_MC, rk5);
        FU_AES_ROUNDS8(FU_AESE_MC, rk6);
        FU_AES_ROUNDS8(FU_AESE_MC, rk7);
        FU_AES_ROUNDS8(FU_AESE_MC, rk8);
        FU_AES_ROUNDS8(FU_AESE_MC, rk9);
        FU_AES_ROUNDS8(FU_AESE_MC, rk10);
        FU_AES_ROUNDS8(FU_AESE_MC, rk11);
        FU_AES_ROUNDS8(FU_AESE_MC, rk12);
        FU_AES_ROUNDS8(FU_AESE_LAST, rk13);
        FU_AES_ROUNDS8(veorq_u8, rk14);
        vst1q_u8(out,       b0);
        vst1q_u8(out + 16,  b1);
        vst1q_u8(out + 32,  b2);
        vst1q_u8(out + 48,  b3);
        vst1q_u8(out + 64,  b4);
        vst1q_u8(out + 80,  b5);
        vst1q_u8(out + 96,  b6);
        vst1q_u8(out + 112, b7);
    }
    while (1) {
        size_t take = len < 32 ? len : 32;
        int rekey = take == 0;
        unsigned char tmp[32];
        uint8x16_t x0 = vreinterpretq_u8_u64(c); c = vaddq_u64(c, one);
        uint8x16_t x1 = vreinterpretq_u8_u64(c);
        if (rekey || take > 16) c = vaddq_u64(c, one);
        for (int r = 0; r < 13; r++) { x0 = FU_AESE_MC(x0, rk[r]); x1 = FU_AESE_MC(x1, rk[r]); }
        x0 = veorq_u8(vaeseq_u8(x0, rk13), rk14);
        x1 = veorq_u8(vaeseq_u8(x1, rk13), rk14);
        if (rekey) {
            vst1q_u8(key,      x0);
            vst1q_u8(key + 16, x1);
            break;
        }
        vst1q_u8(tmp,      x0);
        vst1q_u8(tmp + 16, x1);
        memcpy(out, tmp, take);
        ZEND_SECURE_ZERO(tmp, sizeof(tmp));
        out += take; len -= take;
    }
    vst1q_u8(ctr, vreinterpretq_u8_u64(c));
    ZEND_SECURE_ZERO(rk, sizeof(rk));
}
#endif /* FU_AES_ARM */

#if defined(FU_AES_X86) || defined(FU_AES_ARM)
# define FU_HAVE_AES 1

/* Known-answer test through the production CTR path. The counter is started
   `blk` blocks before the FIPS-197 C.3 plaintext so that block `blk` of the
   keystream must equal the C.3 ciphertext; the cases cover 8-way lanes, the
   two-block tail, a partial block, both new-key blocks, and counter advance. */
static int fu_aes_selftest(void) {
    static const unsigned char fips_key[32] = {
        0x00,0x01,0x02,0x03,0x04,0x05,0x06,0x07,0x08,0x09,0x0a,0x0b,0x0c,0x0d,0x0e,0x0f,
        0x10,0x11,0x12,0x13,0x14,0x15,0x16,0x17,0x18,0x19,0x1a,0x1b,0x1c,0x1d,0x1e,0x1f };
    static const unsigned char fips_pt[16] = {
        0x00,0x11,0x22,0x33,0x44,0x55,0x66,0x77,0x88,0x99,0xaa,0xbb,0xcc,0xdd,0xee,0xff };
    static const unsigned char fips_ct[16] = {
        0x8e,0xa2,0xb7,0xca,0x51,0x67,0x45,0xbf,0xea,0xfc,0x49,0x90,0x4b,0x49,0x60,0x89 };
    /* NIST SP 800-38A F.5.5 (CTR-AES256), block #1 input -> output block */
    static const unsigned char sp_key[32] = {
        0x60,0x3d,0xeb,0x10,0x15,0xca,0x71,0xbe,0x2b,0x73,0xae,0xf0,0x85,0x7d,0x77,0x81,
        0x1f,0x35,0x2c,0x07,0x3b,0x61,0x08,0xd7,0x2d,0x98,0x10,0xa3,0x09,0x14,0xdf,0xf4 };
    static const unsigned char sp_in[16] = {
        0xf0,0xf1,0xf2,0xf3,0xf4,0xf5,0xf6,0xf7,0xf8,0xf9,0xfa,0xfb,0xfc,0xfd,0xfe,0xff };
    static const unsigned char sp_out[16] = {
        0x0b,0xdf,0x7d,0xf1,0x59,0x17,0x16,0x33,0x5e,0x9a,0x8b,0x15,0xc8,0x60,0xc5,0x02 };
    /* {len, blk}: blk == ceil(len / 16) or +1 selects a new-key block */
    static const struct { uint16_t len, blk; } cases[] = {
        /* every lane of the 8-way loop, plus lanes 0 and 7 of its second
           iteration (the counter carries across passes) */
        { 128, 0 }, { 128, 1 }, { 128, 2 }, { 128, 3 },
        { 128, 4 }, { 128, 5 }, { 128, 6 }, { 128, 7 }, { 256, 8 }, { 256, 15 },
        /* two-block tail: x0 and x1, a partial x1, and the no-8-way path */
        { 16, 0 }, { 144, 8 }, { 160, 9 }, { 183, 10 }, { 183, 11 },
        /* the two new-key blocks after 3 (tail only) and 12 (8-way + tail) blocks */
        { 48, 3 }, { 48, 4 }, { 183, 12 }, { 183, 13 },
    };
    unsigned char out[272], key[32], ctr[16];
    for (size_t i = 0; i < sizeof(cases) / sizeof(cases[0]); i++) {
        size_t len = cases[i].len, blk = cases[i].blk, nblk = (len + 15) / 16;
        uint64_t lo, lo_after, hi, hi_after;
        memcpy(key, fips_key, 32);
        memcpy(ctr, fips_pt, 16);
        memcpy(&lo, ctr, 8); memcpy(&hi, ctr + 8, 8);
        lo -= blk;
        memcpy(ctr, &lo, 8);
        memset(out, 0xa5, sizeof(out));
        fu_aes_ctr(key, ctr, out, len);
        if (blk < nblk) {
            size_t cmp = len - blk * 16 < 16 ? len - blk * 16 : 16;
            if (memcmp(out + blk * 16, fips_ct, cmp) != 0) return 0;
        } else if (memcmp(key + (blk - nblk) * 16, fips_ct, 16) != 0) {
            return 0;
        }
        if (out[len] != 0xa5) return 0;  /* wrote past len */
        memcpy(&lo_after, ctr, 8); memcpy(&hi_after, ctr + 8, 8);
        if (lo_after != lo + nblk + 2 || hi_after != hi) return 0;
    }
    memcpy(key, sp_key, 32);
    memcpy(ctr, sp_in, 16);
    fu_aes_ctr(key, ctr, out, 16);
    return memcmp(out, sp_out, 16) == 0;
}

/* Fills dst from the DRBG, FU_DRBG_CHUNK bytes per key. FAILURE only when an
   OS reseed fails (exception thrown by php_random_bytes_throw). */
static zend_result fu_drbg_fill(unsigned char *dst, size_t n) {
    while (n > 0) {
        if (UNEXPECTED(!FAST_UUID_G(drbg_seeded) || FAST_UUID_G(drbg_since_seed) >= FU_DRBG_RESEED_BYTES)) {
            unsigned char seed[48];
            if (php_random_bytes_throw(seed, sizeof(seed)) == FAILURE) return FAILURE;
            for (int i = 0; i < 32; i++) FAST_UUID_G(drbg_key)[i] ^= seed[i];
            for (int i = 0; i < 16; i++) FAST_UUID_G(drbg_ctr)[i] ^= seed[32 + i];
            ZEND_SECURE_ZERO(seed, sizeof(seed));
            FAST_UUID_G(drbg_seeded) = 1;
            FAST_UUID_G(drbg_since_seed) = 0;
        }
        size_t take = n < FU_DRBG_CHUNK ? n : FU_DRBG_CHUNK;
        fu_aes_ctr(FAST_UUID_G(drbg_key), FAST_UUID_G(drbg_ctr), dst, take);
        FAST_UUID_G(drbg_since_seed) += take;
        dst += take; n -= take;
    }
    return SUCCESS;
}
#endif /* FU_AES_X86 || FU_AES_ARM */

/* Unbuffered CSPRNG fill. Requests larger than the buffer stream from the
   DRBG as well: same per-chunk key erasure and reseed accounting, and several
   times the OS CSPRNG's throughput. */
static zend_result fu_rand_fill(unsigned char *dst, size_t n) {
#ifdef FU_HAVE_AES
    if (EXPECTED(fu_aes_state == FU_AES_ACTIVE)) return fu_drbg_fill(dst, n);
#endif
    return php_random_bytes_throw(dst, n);
}

/* crypto-secure, batched. Returns FAILURE (with an exception thrown by
   php_random_bytes_throw) if the CSPRNG fails; dst is zeroed on failure so
   callers never read uninitialized bytes. */
static zend_never_inline zend_result fu_rand_slow(unsigned char *dst, size_t n) {
    if (UNEXPECTED(n > sizeof(FAST_UUID_G(rbuf)))) {
        if (fu_rand_fill(dst, n) == FAILURE) { memset(dst, 0, n); return FAILURE; }
        return SUCCESS;
    }
    if (fu_rand_fill(FAST_UUID_G(rbuf), sizeof(FAST_UUID_G(rbuf))) == FAILURE) {
        memset(dst, 0, n);
        return FAILURE;
    }
    memcpy(dst, FAST_UUID_G(rbuf), n);
    FAST_UUID_G(rpos) = n;
    return SUCCESS;
}

static zend_always_inline zend_result fu_rand(unsigned char *dst, size_t n) {
    size_t rpos = FAST_UUID_G(rpos);
    if (UNEXPECTED(n > sizeof(FAST_UUID_G(rbuf)) - rpos)) {
        return fu_rand_slow(dst, n);
    }
    memcpy(dst, FAST_UUID_G(rbuf) + rpos, n);
    FAST_UUID_G(rpos) = rpos + n;
    return SUCCESS;
}

/* non-crypto xoshiro256** for uuid_v4_fast() */
static zend_always_inline uint64_t fu_rotl(uint64_t x, int k) { return (x << k) | (x >> (64 - k)); }

static zend_never_inline zend_result fu_xs_seed(uint64_t *s) {
    if (fu_rand((unsigned char *)s, 32) == FAILURE) return FAILURE;
    if ((s[0] | s[1] | s[2] | s[3]) == 0) s[0] = 0x9e3779b97f4a7c15ULL;
    FAST_UUID_G(prng_seeded) = 1;
    return SUCCESS;
}

static zend_always_inline uint64_t fu_xs_next_raw(uint64_t *s) {
    uint64_t r = fu_rotl(s[1] * 5, 7) * 9;
    uint64_t t = s[1] << 17;
    s[2] ^= s[0]; s[3] ^= s[1]; s[1] ^= s[2]; s[0] ^= s[3];
    s[2] ^= t;    s[3] = fu_rotl(s[3], 45);
    return r;
}

static zend_always_inline zend_result fu_rand_u64(uint64_t *out) {
    return fu_rand((unsigned char *)out, sizeof(*out));
}

#ifndef PHP_WIN32
/* Discard inherited CSPRNG, xoshiro, and v7 state to prevent duplicate UUIDs.
   Under ZTS, only the forking thread's globals are reachable. A child forked
   off the PHP thread must re-exec or restart PHP before generating UUIDs;
   other threads' inherited state cannot be reset here. */
static void fu_atfork_child(void) {
#ifdef ZTS
    /* A fork() issued by a native thread that never entered PHP (another
       extension, an embedded library) has no TSRM cache; FAST_UUID_G would
       offset off a NULL base. That thread has no PHP globals to reset. */
    if (!tsrm_get_ls_cache()) return;
#endif
    /* Wipe residual entropy so a memory disclosure in the child cannot
       recover the parent's remaining CSPRNG buffer, DRBG key, or xoshiro
       seed. The zeroed DRBG key is re-keyed from the OS before its next use. */
    memset(FAST_UUID_G(rbuf), 0, sizeof(FAST_UUID_G(rbuf)));
    memset(FAST_UUID_G(prng_s), 0, sizeof(FAST_UUID_G(prng_s)));
    memset(FAST_UUID_G(drbg_key), 0, sizeof(FAST_UUID_G(drbg_key)));
    memset(FAST_UUID_G(drbg_ctr), 0, sizeof(FAST_UUID_G(drbg_ctr)));
    FAST_UUID_G(drbg_seeded) = 0;                  /* force OS reseed */
    FAST_UUID_G(drbg_since_seed) = 0;
    FAST_UUID_G(rpos) = sizeof(FAST_UUID_G(rbuf)); /* force refill on next fu_rand */
    FAST_UUID_G(prng_seeded) = 0;                  /* force xoshiro reseed */
    FAST_UUID_G(v7_key) = 0;                        /* force v7 reseed (new key > 0) */
    FAST_UUID_G(v7_randb) = 0;
    FAST_UUID_G(v7_initialized) = 0;
    FAST_UUID_G(greg_last) = 0;
    FAST_UUID_G(v2_last) = 0;                    /* reset v2 visible-timestamp mark */
}
#endif

/* ------------------------------------------------------------------ */
/* hardware SHA-1 (v5) and inline MD5 (v3)                            */
/* ------------------------------------------------------------------ */

#if defined(FU_X86) && !defined(FU_DISABLE_SHA1)
# define FU_SHA1_X86 1
# if defined(_MSC_VER)
#  define FU_TARGET_SHA1
# else
#  define FU_TARGET_SHA1 __attribute__((target("sha,sse4.1,ssse3")))
# endif
#elif defined(FU_AARCH64_LE) && !defined(FU_DISABLE_SHA1)
# if defined(__ARM_FEATURE_SHA2) || defined(__ARM_FEATURE_CRYPTO)
#  define FU_SHA1_ARM 1
#  define FU_TARGET_SHA1
# elif defined(__GNUC__) && !defined(__clang__) && __GNUC__ >= 8
#  define FU_SHA1_ARM 1
#  define FU_TARGET_SHA1 __attribute__((target("+crypto")))
# endif
# ifdef FU_SHA1_ARM
#  if defined(__linux__)
#   include <sys/auxv.h>
#   ifndef HWCAP_SHA1
#    define HWCAP_SHA1 (1UL << 5)
#   endif
#  endif
# endif
#endif

#if defined(FU_SHA1_X86) || defined(FU_SHA1_ARM)
# define FU_HAVE_SHA1_HW 1
# ifdef FU_SHA1_X86
#  define FU_SHA1_NAME "SHA-NI"
# else
#  define FU_SHA1_NAME "ARMv8 SHA-1"
# endif
static int fu_sha1_active = 0;  /* set once in MINIT after selftest */

static zend_always_inline void fu_store_be32(unsigned char *dst, uint32_t v) {
# if defined(WORDS_BIGENDIAN)
    memcpy(dst, &v, 4);
# elif defined(_MSC_VER)
    uint32_t be = _byteswap_ulong(v);
    memcpy(dst, &be, 4);
# else
    uint32_t be = __builtin_bswap32(v);
    memcpy(dst, &be, 4);
# endif
}
#endif

#ifdef FU_SHA1_X86
static int fu_detect_sha1(void) {
# if defined(_MSC_VER)
    int regs[4];
    __cpuid(regs, 0);
    if (regs[0] < 7) return 0;
    __cpuid(regs, 1);
    if (((regs[2] >> 9) & 1) == 0 || ((regs[2] >> 19) & 1) == 0) return 0;
    __cpuidex(regs, 7, 0);
    return (regs[1] >> 29) & 1;
# else
    unsigned int eax, ebx, ecx, edx;
    if (__get_cpuid_max(0, NULL) < 7) return 0;
    if (!__get_cpuid(1, &eax, &ebx, &ecx, &edx)) return 0;
    if (((ecx >> 9) & 1) == 0 || ((ecx >> 19) & 1) == 0) return 0;
    if (!__get_cpuid_count(7, 0, &eax, &ebx, &ecx, &edx)) return 0;
    return (ebx >> 29) & 1;
# endif
}

# define FU_SHA1_X86_MID(ecur, enxt, m0, m1, m2, m3, fn) do { \
    (ecur) = _mm_sha1nexte_epu32((ecur), (m0)); \
    (enxt) = abcd; \
    abcd = _mm_sha1rnds4_epu32(abcd, (ecur), (fn)); \
    (m1) = _mm_sha1msg2_epu32((m1), (m0)); \
    (m3) = _mm_sha1msg1_epu32((m3), (m0)); \
    (m2) = _mm_xor_si128((m2), (m0)); \
} while (0)

FU_TARGET_SHA1 static void fu_sha1_block_hw(uint32_t st[5], const unsigned char blk[64]) {
    const __m128i shuf_mask = _mm_set_epi64x(0x0001020304050607ULL, 0x08090a0b0c0d0e0fULL);
    __m128i abcd = _mm_shuffle_epi32(_mm_loadu_si128((const __m128i *)st), 0x1B);
    __m128i e0   = _mm_set_epi32((int)st[4], 0, 0, 0);
    __m128i abcd_save = abcd, e0_save = e0;
    __m128i e1, msg0, msg1, msg2, msg3;

    /* Rounds 0..3 */
    msg0 = _mm_shuffle_epi8(_mm_loadu_si128((const __m128i *)(blk + 0)), shuf_mask);
    e0   = _mm_add_epi32(e0, msg0);
    e1   = abcd;
    abcd = _mm_sha1rnds4_epu32(abcd, e0, 0);
    /* Rounds 4..7 */
    msg1 = _mm_shuffle_epi8(_mm_loadu_si128((const __m128i *)(blk + 16)), shuf_mask);
    e1   = _mm_sha1nexte_epu32(e1, msg1);
    e0   = abcd;
    abcd = _mm_sha1rnds4_epu32(abcd, e1, 0);
    msg0 = _mm_sha1msg1_epu32(msg0, msg1);
    /* Rounds 8..11 */
    msg2 = _mm_shuffle_epi8(_mm_loadu_si128((const __m128i *)(blk + 32)), shuf_mask);
    e0   = _mm_sha1nexte_epu32(e0, msg2);
    e1   = abcd;
    abcd = _mm_sha1rnds4_epu32(abcd, e0, 0);
    msg1 = _mm_sha1msg1_epu32(msg1, msg2);
    msg0 = _mm_xor_si128(msg0, msg2);
    /* Rounds 12..15 */
    msg3 = _mm_shuffle_epi8(_mm_loadu_si128((const __m128i *)(blk + 48)), shuf_mask);
    e1   = _mm_sha1nexte_epu32(e1, msg3);
    e0   = abcd;
    abcd = _mm_sha1rnds4_epu32(abcd, e1, 0);
    msg0 = _mm_sha1msg2_epu32(msg0, msg3);
    msg2 = _mm_sha1msg1_epu32(msg2, msg3);
    msg1 = _mm_xor_si128(msg1, msg3);

    FU_SHA1_X86_MID(e0, e1, msg0, msg1, msg2, msg3, 0); /* 16..19 */
    FU_SHA1_X86_MID(e1, e0, msg1, msg2, msg3, msg0, 1); /* 20..23 */
    FU_SHA1_X86_MID(e0, e1, msg2, msg3, msg0, msg1, 1); /* 24..27 */
    FU_SHA1_X86_MID(e1, e0, msg3, msg0, msg1, msg2, 1); /* 28..31 */
    FU_SHA1_X86_MID(e0, e1, msg0, msg1, msg2, msg3, 1); /* 32..35 */
    FU_SHA1_X86_MID(e1, e0, msg1, msg2, msg3, msg0, 1); /* 36..39 */
    FU_SHA1_X86_MID(e0, e1, msg2, msg3, msg0, msg1, 2); /* 40..43 */
    FU_SHA1_X86_MID(e1, e0, msg3, msg0, msg1, msg2, 2); /* 44..47 */
    FU_SHA1_X86_MID(e0, e1, msg0, msg1, msg2, msg3, 2); /* 48..51 */
    FU_SHA1_X86_MID(e1, e0, msg1, msg2, msg3, msg0, 2); /* 52..55 */
    FU_SHA1_X86_MID(e0, e1, msg2, msg3, msg0, msg1, 2); /* 56..59 */
    FU_SHA1_X86_MID(e1, e0, msg3, msg0, msg1, msg2, 3); /* 60..63 */
    FU_SHA1_X86_MID(e0, e1, msg0, msg1, msg2, msg3, 3); /* 64..67 */

    /* Rounds 68..71 */
    e1   = _mm_sha1nexte_epu32(e1, msg1);
    e0   = abcd;
    abcd = _mm_sha1rnds4_epu32(abcd, e1, 3);
    msg2 = _mm_sha1msg2_epu32(msg2, msg1);
    msg3 = _mm_xor_si128(msg3, msg1);
    /* Rounds 72..75 */
    e0   = _mm_sha1nexte_epu32(e0, msg2);
    e1   = abcd;
    abcd = _mm_sha1rnds4_epu32(abcd, e0, 3);
    msg3 = _mm_sha1msg2_epu32(msg3, msg2);
    /* Rounds 76..79 */
    e1   = _mm_sha1nexte_epu32(e1, msg3);
    e0   = abcd;
    abcd = _mm_sha1rnds4_epu32(abcd, e1, 3);

    e0   = _mm_sha1nexte_epu32(e0, e0_save);
    abcd = _mm_shuffle_epi32(_mm_add_epi32(abcd, abcd_save), 0x1B);
    _mm_storeu_si128((__m128i *)st, abcd);
    st[4] = (uint32_t)_mm_extract_epi32(e0, 3);
}
# undef FU_SHA1_X86_MID
#endif /* FU_SHA1_X86 */

#ifdef FU_SHA1_ARM
static int fu_detect_sha1(void) {
# if defined(__linux__)
    return (getauxval(AT_HWCAP) & HWCAP_SHA1) != 0;
# elif defined(__APPLE__)
    return 1;
# else
    return 0;
# endif
}

# define FU_SHA1_ARM_STEP(op, k, ecur, enxt, m0, m1, m2, m3) do { \
    uint32x4_t t_ = vaddq_u32((m0), (k)); \
    (enxt) = vsha1h_u32(vgetq_lane_u32(abcd, 0)); \
    abcd = op(abcd, (ecur), t_); \
    (m0) = vsha1su1q_u32(vsha1su0q_u32((m0), (m1), (m2)), (m3)); \
} while (0)

# define FU_SHA1_ARM_TAIL(op, k, ecur, enxt, m0) do { \
    uint32x4_t t_ = vaddq_u32((m0), (k)); \
    (enxt) = vsha1h_u32(vgetq_lane_u32(abcd, 0)); \
    abcd = op(abcd, (ecur), t_); \
} while (0)

FU_TARGET_SHA1 static void fu_sha1_block_hw(uint32_t st[5], const unsigned char blk[64]) {
    const uint32x4_t k0 = vdupq_n_u32(0x5A827999U);
    const uint32x4_t k1 = vdupq_n_u32(0x6ED9EBA1U);
    const uint32x4_t k2 = vdupq_n_u32(0x8F1BBCDCU);
    const uint32x4_t k3 = vdupq_n_u32(0xCA62C1D6U);
    uint32x4_t abcd = vld1q_u32(st);
    uint32_t e0 = st[4], e1;
    uint32x4_t abcd_save = abcd;
    uint32_t e0_save = e0;

    uint32x4_t m0 = vreinterpretq_u32_u8(vrev32q_u8(vld1q_u8(blk + 0)));
    uint32x4_t m1 = vreinterpretq_u32_u8(vrev32q_u8(vld1q_u8(blk + 16)));
    uint32x4_t m2 = vreinterpretq_u32_u8(vrev32q_u8(vld1q_u8(blk + 32)));
    uint32x4_t m3 = vreinterpretq_u32_u8(vrev32q_u8(vld1q_u8(blk + 48)));

    FU_SHA1_ARM_STEP(vsha1cq_u32, k0, e0, e1, m0, m1, m2, m3); /* 0..3 */
    FU_SHA1_ARM_STEP(vsha1cq_u32, k0, e1, e0, m1, m2, m3, m0); /* 4..7 */
    FU_SHA1_ARM_STEP(vsha1cq_u32, k0, e0, e1, m2, m3, m0, m1); /* 8..11 */
    FU_SHA1_ARM_STEP(vsha1cq_u32, k0, e1, e0, m3, m0, m1, m2); /* 12..15 */
    FU_SHA1_ARM_STEP(vsha1cq_u32, k0, e0, e1, m0, m1, m2, m3); /* 16..19 */

    FU_SHA1_ARM_STEP(vsha1pq_u32, k1, e1, e0, m1, m2, m3, m0); /* 20..23 */
    FU_SHA1_ARM_STEP(vsha1pq_u32, k1, e0, e1, m2, m3, m0, m1); /* 24..27 */
    FU_SHA1_ARM_STEP(vsha1pq_u32, k1, e1, e0, m3, m0, m1, m2); /* 28..31 */
    FU_SHA1_ARM_STEP(vsha1pq_u32, k1, e0, e1, m0, m1, m2, m3); /* 32..35 */
    FU_SHA1_ARM_STEP(vsha1pq_u32, k1, e1, e0, m1, m2, m3, m0); /* 36..39 */

    FU_SHA1_ARM_STEP(vsha1mq_u32, k2, e0, e1, m2, m3, m0, m1); /* 40..43 */
    FU_SHA1_ARM_STEP(vsha1mq_u32, k2, e1, e0, m3, m0, m1, m2); /* 44..47 */
    FU_SHA1_ARM_STEP(vsha1mq_u32, k2, e0, e1, m0, m1, m2, m3); /* 48..51 */
    FU_SHA1_ARM_STEP(vsha1mq_u32, k2, e1, e0, m1, m2, m3, m0); /* 52..55 */
    FU_SHA1_ARM_STEP(vsha1mq_u32, k2, e0, e1, m2, m3, m0, m1); /* 56..59 */

    FU_SHA1_ARM_STEP(vsha1pq_u32, k3, e1, e0, m3, m0, m1, m2); /* 60..63 */
    FU_SHA1_ARM_TAIL(vsha1pq_u32, k3, e0, e1, m0);             /* 64..67 */
    FU_SHA1_ARM_TAIL(vsha1pq_u32, k3, e1, e0, m1);             /* 68..71 */
    FU_SHA1_ARM_TAIL(vsha1pq_u32, k3, e0, e1, m2);             /* 72..75 */
    FU_SHA1_ARM_TAIL(vsha1pq_u32, k3, e1, e0, m3);             /* 76..79 */

    vst1q_u32(st, vaddq_u32(abcd, abcd_save));
    st[4] = e0 + e0_save;
}
# undef FU_SHA1_ARM_STEP
# undef FU_SHA1_ARM_TAIL
#endif /* FU_SHA1_ARM */

#ifdef FU_HAVE_SHA1_HW
static zend_never_inline void fu_sha1_process_hw(
    uint32_t st[5], const unsigned char *p1, size_t l1, const unsigned char *p2, size_t l2) {
    unsigned char blk[64];
    size_t pos = 0;
    uint64_t total_bits = ((uint64_t)l1 + (uint64_t)l2) << 3;
    const unsigned char *bufs[2] = { p1, p2 };
    size_t lens[2] = { l1, l2 };
    for (int idx = 0; idx < 2; idx++) {
        const unsigned char *p = bufs[idx];
        size_t rem = lens[idx];
        if (pos > 0 && rem > 0) {
            size_t take = 64 - pos;
            if (take > rem) take = rem;
            memcpy(blk + pos, p, take);
            pos += take; p += take; rem -= take;
            if (pos == 64) {
                fu_sha1_block_hw(st, blk);
                pos = 0;
            }
        }
        while (rem >= 64) {
            fu_sha1_block_hw(st, p);
            p += 64; rem -= 64;
        }
        if (rem > 0) {
            memcpy(blk, p, rem);
            pos = rem;
        }
    }
    blk[pos++] = 0x80;
    if (pos > 56) {
        memset(blk + pos, 0, 64 - pos);
        fu_sha1_block_hw(st, blk);
        pos = 0;
    }
    memset(blk + pos, 0, 56 - pos);
    fu_store_be64(blk + 56, total_bits);
    fu_sha1_block_hw(st, blk);
}

static void fu_sha1_v5_hw(unsigned char b[16], const unsigned char ns[16], const char *name, size_t nl) {
    uint32_t st[5] = { 0x67452301U, 0xEFCDAB89U, 0x98BADCFEU, 0x10325476U, 0xC3D2E1F0U };
    if (EXPECTED(nl <= 39)) {
        unsigned char blk[64];
        memcpy(blk, ns, 16);
        if (nl) memcpy(blk + 16, name, nl);
        blk[16 + nl] = 0x80;
        memset(blk + 17 + nl, 0, 56 - (17 + nl));
        fu_store_be64(blk + 56, ((uint64_t)(16 + nl)) << 3);
        fu_sha1_block_hw(st, blk);
    } else {
        fu_sha1_process_hw(st, ns, 16, (const unsigned char *)name, nl);
    }
    fu_store_be32(b,      st[0]);
    fu_store_be32(b + 4,  st[1]);
    fu_store_be32(b + 8,  st[2]);
    fu_store_be32(b + 12, st[3]);
    b[6] = (b[6] & 0x0f) | 0x50;
    b[8] = (b[8] & 0x3f) | 0x80;
}

static int fu_sha1_selftest(void) {
    static const unsigned char exp_empty[20] = {
        0xda,0x39,0xa3,0xee,0x5e,0x6b,0x4b,0x0d,0x32,0x55,
        0xbf,0xef,0x95,0x60,0x18,0x90,0xaf,0xd8,0x07,0x09
    };
    static const unsigned char exp_abc[20] = {
        0xa9,0x99,0x3e,0x36,0x47,0x06,0x81,0x6a,0xba,0x3e,
        0x25,0x71,0x78,0x50,0xc2,0x6c,0x9c,0xd0,0xd8,0x9d
    };
    static const char msg56[] = "abcdbcdecdefdefgefghfghighijhijkijkljklmklmnlmnomnopnopq";
    static const unsigned char exp_56[20] = {
        0x84,0x98,0x3e,0x44,0x1c,0x3b,0xd2,0x6e,0xba,0xae,
        0x4a,0xa1,0xf9,0x51,0x29,0xe5,0xe5,0x46,0x70,0xf1
    };
    static const unsigned char ns_dns[16] = {
        0x6b,0xa7,0xb8,0x10,0x9d,0xad,0x11,0xd1,0x80,0xb4,0x00,0xc0,0x4f,0xd4,0x30,0xc8
    };
    static const unsigned char exp_v5[16] = {
        0x2e,0xd6,0x65,0x7d,0xe9,0x27,0x56,0x8b,0x95,0xe1,0x26,0x65,0xa8,0xae,0xa6,0xa2
    };
    const struct { const char *msg; size_t len; const unsigned char *exp; } kat[3] = {
        { "",    0,  exp_empty },
        { "abc", 3,  exp_abc },
        { msg56, 56, exp_56 },
    };
    for (int i = 0; i < 3; i++) {
        uint32_t st[5] = { 0x67452301U, 0xEFCDAB89U, 0x98BADCFEU, 0x10325476U, 0xC3D2E1F0U };
        unsigned char dig[20];
        fu_sha1_process_hw(st, (const unsigned char *)kat[i].msg, kat[i].len, NULL, 0);
        for (int j = 0; j < 5; j++) fu_store_be32(dig + j * 4, st[j]);
        if (memcmp(dig, kat[i].exp, 20) != 0) return 0;
    }
    unsigned char v5[16];
    fu_sha1_v5_hw(v5, ns_dns, "www.example.com", 15);
    return memcmp(v5, exp_v5, 16) == 0;
}
#endif /* FU_HAVE_SHA1_HW */

#ifndef WORDS_BIGENDIAN
static zend_always_inline uint32_t fu_rotl32(uint32_t x, int s) {
    return (x << s) | (x >> (32 - s));
}

# define FU_MD5_STEP(f, a, b, c, d, x, s, ac) do { \
    (a) += f((b), (c), (d)) + (x) + (uint32_t)(ac); \
    (a) = fu_rotl32((a), (s)) + (b); \
} while (0)
# define FU_MD5_F(x, y, z) (((x) & (y)) | (~(x) & (z)))
# define FU_MD5_G(x, y, z) (((x) & (z)) | ((y) & ~(z)))
# define FU_MD5_H(x, y, z) ((x) ^ (y) ^ (z))
# define FU_MD5_I(x, y, z) ((y) ^ ((x) | ~(z)))

static void fu_md5_block(uint32_t st[4], const unsigned char blk[64]) {
    uint32_t x[16];
    memcpy(x, blk, 64);
    uint32_t a = st[0], b = st[1], c = st[2], d = st[3];

    FU_MD5_STEP(FU_MD5_F, a, b, c, d, x[ 0],  7, 0xd76aa478U);
    FU_MD5_STEP(FU_MD5_F, d, a, b, c, x[ 1], 12, 0xe8c7b756U);
    FU_MD5_STEP(FU_MD5_F, c, d, a, b, x[ 2], 17, 0x242070dbU);
    FU_MD5_STEP(FU_MD5_F, b, c, d, a, x[ 3], 22, 0xc1bdceeeU);
    FU_MD5_STEP(FU_MD5_F, a, b, c, d, x[ 4],  7, 0xf57c0fafU);
    FU_MD5_STEP(FU_MD5_F, d, a, b, c, x[ 5], 12, 0x4787c62aU);
    FU_MD5_STEP(FU_MD5_F, c, d, a, b, x[ 6], 17, 0xa8304613U);
    FU_MD5_STEP(FU_MD5_F, b, c, d, a, x[ 7], 22, 0xfd469501U);
    FU_MD5_STEP(FU_MD5_F, a, b, c, d, x[ 8],  7, 0x698098d8U);
    FU_MD5_STEP(FU_MD5_F, d, a, b, c, x[ 9], 12, 0x8b44f7afU);
    FU_MD5_STEP(FU_MD5_F, c, d, a, b, x[10], 17, 0xffff5bb1U);
    FU_MD5_STEP(FU_MD5_F, b, c, d, a, x[11], 22, 0x895cd7beU);
    FU_MD5_STEP(FU_MD5_F, a, b, c, d, x[12],  7, 0x6b901122U);
    FU_MD5_STEP(FU_MD5_F, d, a, b, c, x[13], 12, 0xfd987193U);
    FU_MD5_STEP(FU_MD5_F, c, d, a, b, x[14], 17, 0xa679438eU);
    FU_MD5_STEP(FU_MD5_F, b, c, d, a, x[15], 22, 0x49b40821U);

    FU_MD5_STEP(FU_MD5_G, a, b, c, d, x[ 1],  5, 0xf61e2562U);
    FU_MD5_STEP(FU_MD5_G, d, a, b, c, x[ 6],  9, 0xc040b340U);
    FU_MD5_STEP(FU_MD5_G, c, d, a, b, x[11], 14, 0x265e5a51U);
    FU_MD5_STEP(FU_MD5_G, b, c, d, a, x[ 0], 20, 0xe9b6c7aaU);
    FU_MD5_STEP(FU_MD5_G, a, b, c, d, x[ 5],  5, 0xd62f105dU);
    FU_MD5_STEP(FU_MD5_G, d, a, b, c, x[10],  9, 0x02441453U);
    FU_MD5_STEP(FU_MD5_G, c, d, a, b, x[15], 14, 0xd8a1e681U);
    FU_MD5_STEP(FU_MD5_G, b, c, d, a, x[ 4], 20, 0xe7d3fbc8U);
    FU_MD5_STEP(FU_MD5_G, a, b, c, d, x[ 9],  5, 0x21e1cde6U);
    FU_MD5_STEP(FU_MD5_G, d, a, b, c, x[14],  9, 0xc33707d6U);
    FU_MD5_STEP(FU_MD5_G, c, d, a, b, x[ 3], 14, 0xf4d50d87U);
    FU_MD5_STEP(FU_MD5_G, b, c, d, a, x[ 8], 20, 0x455a14edU);
    FU_MD5_STEP(FU_MD5_G, a, b, c, d, x[13],  5, 0xa9e3e905U);
    FU_MD5_STEP(FU_MD5_G, d, a, b, c, x[ 2],  9, 0xfcefa3f8U);
    FU_MD5_STEP(FU_MD5_G, c, d, a, b, x[ 7], 14, 0x676f02d9U);
    FU_MD5_STEP(FU_MD5_G, b, c, d, a, x[12], 20, 0x8d2a4c8aU);

    FU_MD5_STEP(FU_MD5_H, a, b, c, d, x[ 5],  4, 0xfffa3942U);
    FU_MD5_STEP(FU_MD5_H, d, a, b, c, x[ 8], 11, 0x8771f681U);
    FU_MD5_STEP(FU_MD5_H, c, d, a, b, x[11], 16, 0x6d9d6122U);
    FU_MD5_STEP(FU_MD5_H, b, c, d, a, x[14], 23, 0xfde5380cU);
    FU_MD5_STEP(FU_MD5_H, a, b, c, d, x[ 1],  4, 0xa4beea44U);
    FU_MD5_STEP(FU_MD5_H, d, a, b, c, x[ 4], 11, 0x4bdecfa9U);
    FU_MD5_STEP(FU_MD5_H, c, d, a, b, x[ 7], 16, 0xf6bb4b60U);
    FU_MD5_STEP(FU_MD5_H, b, c, d, a, x[10], 23, 0xbebfbc70U);
    FU_MD5_STEP(FU_MD5_H, a, b, c, d, x[13],  4, 0x289b7ec6U);
    FU_MD5_STEP(FU_MD5_H, d, a, b, c, x[ 0], 11, 0xeaa127faU);
    FU_MD5_STEP(FU_MD5_H, c, d, a, b, x[ 3], 16, 0xd4ef3085U);
    FU_MD5_STEP(FU_MD5_H, b, c, d, a, x[ 6], 23, 0x04881d05U);
    FU_MD5_STEP(FU_MD5_H, a, b, c, d, x[ 9],  4, 0xd9d4d039U);
    FU_MD5_STEP(FU_MD5_H, d, a, b, c, x[12], 11, 0xe6db99e5U);
    FU_MD5_STEP(FU_MD5_H, c, d, a, b, x[15], 16, 0x1fa27cf8U);
    FU_MD5_STEP(FU_MD5_H, b, c, d, a, x[ 2], 23, 0xc4ac5665U);

    FU_MD5_STEP(FU_MD5_I, a, b, c, d, x[ 0],  6, 0xf4292244U);
    FU_MD5_STEP(FU_MD5_I, d, a, b, c, x[ 7], 10, 0x432aff97U);
    FU_MD5_STEP(FU_MD5_I, c, d, a, b, x[14], 15, 0xab9423a7U);
    FU_MD5_STEP(FU_MD5_I, b, c, d, a, x[ 5], 21, 0xfc93a039U);
    FU_MD5_STEP(FU_MD5_I, a, b, c, d, x[12],  6, 0x655b59c3U);
    FU_MD5_STEP(FU_MD5_I, d, a, b, c, x[ 3], 10, 0x8f0ccc92U);
    FU_MD5_STEP(FU_MD5_I, c, d, a, b, x[10], 15, 0xffeff47dU);
    FU_MD5_STEP(FU_MD5_I, b, c, d, a, x[ 1], 21, 0x85845dd1U);
    FU_MD5_STEP(FU_MD5_I, a, b, c, d, x[ 8],  6, 0x6fa87e4fU);
    FU_MD5_STEP(FU_MD5_I, d, a, b, c, x[15], 10, 0xfe2ce6e0U);
    FU_MD5_STEP(FU_MD5_I, c, d, a, b, x[ 6], 15, 0xa3014314U);
    FU_MD5_STEP(FU_MD5_I, b, c, d, a, x[13], 21, 0x4e0811a1U);
    FU_MD5_STEP(FU_MD5_I, a, b, c, d, x[ 4],  6, 0xf7537e82U);
    FU_MD5_STEP(FU_MD5_I, d, a, b, c, x[11], 10, 0xbd3af235U);
    FU_MD5_STEP(FU_MD5_I, c, d, a, b, x[ 2], 15, 0x2ad7d2bbU);
    FU_MD5_STEP(FU_MD5_I, b, c, d, a, x[ 9], 21, 0xeb86d391U);

    st[0] += a; st[1] += b; st[2] += c; st[3] += d;
}

# undef FU_MD5_STEP
# undef FU_MD5_F
# undef FU_MD5_G
# undef FU_MD5_H
# undef FU_MD5_I

static zend_never_inline void fu_md5_process(
    uint32_t st[4], const unsigned char ns[16], const unsigned char *name, size_t nl) {
    unsigned char blk[64];
    uint64_t total_bits = ((uint64_t)16 + (uint64_t)nl) << 3;
    memcpy(blk, ns, 16);
    size_t pos = 16;
    if (nl >= 48) {
        memcpy(blk + 16, name, 48);
        fu_md5_block(st, blk);
        name += 48; nl -= 48;
        while (nl >= 64) {
            fu_md5_block(st, name);
            name += 64; nl -= 64;
        }
        if (nl > 0) memcpy(blk, name, nl);
        pos = nl;
    } else if (nl > 0) {
        memcpy(blk + 16, name, nl);
        pos = 16 + nl;
    }
    blk[pos++] = 0x80;
    if (pos > 56) {
        memset(blk + pos, 0, 64 - pos);
        fu_md5_block(st, blk);
        pos = 0;
    }
    memset(blk + pos, 0, 56 - pos);
    memcpy(blk + 56, &total_bits, 8);
    fu_md5_block(st, blk);
}

static void fu_md5_v3(unsigned char b[16], const unsigned char ns[16], const char *name, size_t nl) {
    uint32_t st[4] = { 0x67452301U, 0xEFCDAB89U, 0x98BADCFEU, 0x10325476U };
    if (EXPECTED(nl <= 39)) {
        unsigned char blk[64];
        uint64_t total_bits = ((uint64_t)16 + (uint64_t)nl) << 3;
        memcpy(blk, ns, 16);
        if (nl) memcpy(blk + 16, name, nl);
        blk[16 + nl] = 0x80;
        memset(blk + 17 + nl, 0, 56 - (17 + nl));
        memcpy(blk + 56, &total_bits, 8);
        fu_md5_block(st, blk);
    } else {
        fu_md5_process(st, ns, (const unsigned char *)name, nl);
    }
    memcpy(b, st, 16);
    b[6] = (b[6] & 0x0f) | 0x30;
    b[8] = (b[8] & 0x3f) | 0x80;
}
#endif /* !WORDS_BIGENDIAN */

/* ------------------------------------------------------------------ */
/* generators                                                         */
/* ------------------------------------------------------------------ */

/* RFC 9562 version nibble (high of b[6]) + RFC variant bits on b[8]. */
static zend_always_inline void fu_set_ver_var(unsigned char *b, unsigned char ver_hi) {
    b[6] = (b[6] & 0x0f) | ver_hi;
    b[8] = (b[8] & 0x3f) | 0x80;
}

static zend_always_inline zend_result fu_gen_v4(unsigned char *b) {
    if (fu_rand(b, 16) == FAILURE) return FAILURE;
    fu_set_ver_var(b, 0x40);
    return SUCCESS;
}

static zend_always_inline zend_result fu_gen_v4_fast(unsigned char *b) {
    uint64_t *s = FAST_UUID_G(prng_s);
    if (UNEXPECTED(!FAST_UUID_G(prng_seeded))) {
        if (fu_xs_seed(s) == FAILURE) return FAILURE;
    }
    uint64_t a = fu_xs_next_raw(s);
    uint64_t c = fu_xs_next_raw(s);
    memcpy(b, &a, 8); memcpy(b + 8, &c, 8);
    fu_set_ver_var(b, 0x40);
    return SUCCESS;
}

/* Randomize clock_seq and node in one CSPRNG draw when neither is supplied. */
static zend_always_inline zend_result fu_lay_clockseq_node(unsigned char *b, const unsigned char *node, int clockseq) {
    if (EXPECTED(clockseq < 0 && !node)) {
        if (fu_rand(b + 8, 8) == FAILURE) return FAILURE;
        b[8]  = (b[8] & 0x3f) | 0x80;
        b[10] |= 0x01; /* multicast bit per RFC 9562 */
        return SUCCESS;
    }
    unsigned char rnode[6];
    if (clockseq < 0) {
        unsigned char rc[2];
        if (fu_rand(rc, 2) == FAILURE) return FAILURE;
        clockseq = ((rc[0] << 8) | rc[1]) & 0x3fff;
    }
    if (!node) {
        if (fu_rand(rnode, 6) == FAILURE) return FAILURE;
        rnode[0] |= 0x01;
        node = rnode;
    }
    b[8] = ((clockseq >> 8) & 0x3f) | 0x80; /* variant + clock_seq_hi */
    b[9] = clockseq & 0xff;                 /* clock_seq_low */
    memcpy(b + 10, node, 6);
    return SUCCESS;
}

/* Lay out a v1 UUID from a Gregorian 100ns timestamp. Pass node = NULL or
   clockseq = -1 to randomize that field. */
static zend_always_inline zend_result fu_lay_v1(unsigned char *b, uint64_t g, const unsigned char *node, int clockseq) {
    uint64_t hi = ((g & 0xffffffffULL) << 32)
                | ((g >> 16) & 0xffff0000ULL)
                | 0x1000ULL
                | ((g >> 48) & 0x0fffULL);
    fu_store_be64(b, hi);
    return fu_lay_clockseq_node(b, node, clockseq);
}

#ifdef PHP_WIN32
/* PHP 8.1/8.2's x86 Windows SDK omits this import (LNK2019). Resolve once in
   single-threaded MINIT; pre-Win8 falls back to GetSystemTimeAsFileTime. */
typedef void (WINAPI *fu_precise_time_fn)(LPFILETIME);
static fu_precise_time_fn fu_precise_time = NULL;

static zend_always_inline zend_result fu_win_unix_t100(uint64_t *out) {
    FILETIME ft;
    if (fu_precise_time) {
        fu_precise_time(&ft);
    } else {
        GetSystemTimeAsFileTime(&ft);
    }
    uint64_t t100 = ((uint64_t)ft.dwHighDateTime << 32) | ft.dwLowDateTime;
    if (UNEXPECTED(t100 < 116444736000000000ULL)) {
        zend_throw_exception(fu_ex_unsupported, "System clock is before the Unix epoch", 0);
        return FAILURE;
    }
    t100 -= 116444736000000000ULL; /* 1601-01-01 .. 1970-01-01 in 100ns ticks */
    if (UNEXPECTED(t100 > UINT64_MAX / 100ULL)) { /* nanosecond product would wrap (~year 2554) */
        zend_throw_exception(fu_ex_unsupported, "System clock too far in the future for nanosecond representation", 0);
        return FAILURE;
    }
    *out = t100;
    return SUCCESS;
}
#else
static zend_never_inline zend_result fu_clock_fail(int err) {
    zend_throw_exception_ex(fu_ex_unsupported, 0, "clock_gettime(CLOCK_REALTIME) failed: %s", strerror(err));
    return FAILURE;
}

static zend_always_inline zend_result fu_posix_clock(struct timespec *ts) {
    if (UNEXPECTED(clock_gettime(CLOCK_REALTIME, ts) != 0)) {
        return fu_clock_fail(errno);
    }
    if (UNEXPECTED(ts->tv_sec < 0)) {
        zend_throw_exception(fu_ex_unsupported, "System clock is before the Unix epoch", 0);
        return FAILURE;
    }
    if (UNEXPECTED((uint64_t)ts->tv_sec >= 18446744073ULL
        && (uint64_t)ts->tv_sec > (UINT64_MAX - (uint64_t)ts->tv_nsec) / 1000000000ULL)) {
        zend_throw_exception(fu_ex_unsupported, "System clock too far in the future for nanosecond representation", 0);
        return FAILURE;
    }
    return SUCCESS;
}
#endif

static zend_always_inline zend_result fu_v7_now_key(uint64_t *key_out) {
#ifdef PHP_WIN32
    uint64_t t100;
    if (fu_win_unix_t100(&t100) == FAILURE) return FAILURE;
    uint64_t ms = t100 / 10000ULL;
    uint32_t rem = (uint32_t)(t100 - ms * 10000ULL);
    uint32_t sub = (rem << 12) / 10000U;
    *key_out = (ms << 12) | sub;
    return SUCCESS;
#else
    struct timespec ts;
    if (fu_posix_clock(&ts) == FAILURE) return FAILURE;
    uint32_t ns32 = (uint32_t)ts.tv_nsec;
    uint32_t sub_ms = ns32 / 1000000U;
    uint32_t rem_ns = ns32 - sub_ms * 1000000U;
    uint64_t ms = (uint64_t)ts.tv_sec * 1000ULL + sub_ms;
    uint32_t sub = (rem_ns << 12) / 1000000U;
    *key_out = (ms << 12) | sub;
    return SUCCESS;
#endif
}

static zend_always_inline zend_result fu_greg_now(uint64_t *out) {
#ifdef PHP_WIN32
    uint64_t t100;
    if (fu_win_unix_t100(&t100) == FAILURE) return FAILURE;
    *out = t100 + FU_GREG_UNIX_EPOCH;
    return SUCCESS;
#else
    struct timespec ts;
    if (fu_posix_clock(&ts) == FAILURE) return FAILURE;
    *out = (uint64_t)ts.tv_sec * 10000000ULL + ((uint32_t)ts.tv_nsec / 100U) + FU_GREG_UNIX_EPOCH;
    return SUCCESS;
#endif
}

static zend_always_inline zend_result fu_greg_monotonic(uint64_t *out) {
    uint64_t g;
    if (fu_greg_now(&g) == FAILURE) return FAILURE;
    if (g <= FAST_UUID_G(greg_last)) {
        if (UNEXPECTED(FAST_UUID_G(greg_last) == ((1ULL << 60) - 1))) {
            zend_throw_exception(fu_ex_unsupported, "Gregorian timestamp exhausted", 0);
            return FAILURE;
        }
        g = FAST_UUID_G(greg_last) + 1;
    }
    ZEND_ASSERT(g < (1ULL << 60));
    FAST_UUID_G(greg_last) = g;
    *out = g;
    return SUCCESS;
}

/* v2 monotonic clock: v2 overwrites time_low (low 32 timestamp bits) with the
   local identifier, so only the upper 28 bits (time_mid + time_hi) are visible
   in the output. A shared 60-bit mark would be invisible until a bit-32 carry
   (~7 minutes), so v2 keeps its own high-water mark on the visible portion. */
static inline zend_result fu_v2_monotonic(uint64_t *out) {
    uint64_t g;
    if (fu_greg_now(&g) == FAILURE) return FAILURE;
    uint64_t vis = g >> 32; /* visible 28-bit portion */
    if (vis <= FAST_UUID_G(v2_last)) {
        if (UNEXPECTED(FAST_UUID_G(v2_last) == ((1ULL << 28) - 1))) {
            zend_throw_exception(fu_ex_unsupported, "v2 timestamp exhausted", 0);
            return FAILURE;
        }
        vis = FAST_UUID_G(v2_last) + 1;
    }
    ZEND_ASSERT(vis < (1ULL << 28));
    FAST_UUID_G(v2_last) = vis;
    *out = (vis << 32) | (g & 0xffffffffULL);
    return SUCCESS;
}

static zend_always_inline zend_result fu_gen_v1_ex(unsigned char *b, const unsigned char *node, int clockseq) {
    uint64_t g;
    if (fu_greg_monotonic(&g) == FAILURE) return FAILURE;
    return fu_lay_v1(b, g, node, clockseq);
}

static zend_always_inline zend_result fu_gen_v1(unsigned char *b) { return fu_gen_v1_ex(b, NULL, -1); }

/* v6 stores the 60-bit Gregorian timestamp most-significant-first across
   bytes 0..7. */
static zend_always_inline zend_result fu_lay_v6(unsigned char *b, uint64_t g, const unsigned char *node, int clockseq) {
    uint64_t hi = ((g & 0x0ffffffffffff000ULL) << 4) | 0x6000ULL | (g & 0x0fffULL);
    fu_store_be64(b, hi);
    return fu_lay_clockseq_node(b, node, clockseq);
}

static zend_always_inline zend_result fu_gen_v6_ex(unsigned char *b, const unsigned char *node, int clockseq) {
    uint64_t g;
    if (fu_greg_monotonic(&g) == FAILURE) return FAILURE;
    return fu_lay_v6(b, g, node, clockseq);
}

static zend_always_inline zend_result fu_gen_v6(unsigned char *b) { return fu_gen_v6_ex(b, NULL, -1); }

/* DCE Security (v2): v1 time layout, but time_low := local identifier and
   clock_seq_low := local domain; version nibble = 2. The low 32 timestamp bits
   are sacrificed for the local id, so v2 time resolution is coarse.
   monotonic is true only when the caller supplied both node and clockseq.
   Defaulted fields use fresh randomness instead; this does not guarantee
   uniqueness. With a supplied node and defaulted clockseq, only six random
   clockseq bits survive the local-domain overwrite, allowing at most 64 UUIDs
   for a fixed local id, domain, node and visible timestamp. A defaulted node
   also varies randomly, so that 64-value bound does not apply to it. */
static zend_result fu_gen_v2(unsigned char *b, uint32_t local_id, unsigned char local_domain,
                      const unsigned char *node, int clockseq, zend_bool monotonic) {
    uint64_t g;
    if (monotonic) {
        if (fu_v2_monotonic(&g) == FAILURE) return FAILURE;
    } else if (fu_greg_now(&g) == FAILURE) {
        return FAILURE;
    }
    if (fu_lay_v1(b, g, node, clockseq) == FAILURE) return FAILURE;
    b[0] = (local_id >> 24) & 0xff; b[1] = (local_id >> 16) & 0xff;
    b[2] = (local_id >> 8)  & 0xff; b[3] = local_id & 0xff;
    b[6] = (b[6] & 0x0f) | 0x20;    /* version 2 */
    b[9] = local_domain;            /* clock_seq_low := local domain */
    return SUCCESS;
}

/* rand_a holds 12 bits of sub-millisecond clock precision (RFC 9562 6.2,
   Method 3): floor(fraction_of_ms * 4096). rand_b (62 bits) carries the
   monotonic counter / randomness. */
static zend_always_inline void fu_lay_v7_key(unsigned char *b, uint64_t key, uint64_t randb) {
    uint64_t hi = ((key & 0x0ffffffffffff000ULL) << 4) | 0x7000ULL | (key & 0x0fffULL);
    uint64_t lo = 0x8000000000000000ULL | randb;
    fu_store_be64(b, hi);
    fu_store_be64(b + 8, lo);
}

static zend_never_inline zend_result fu_v7_exhausted(void) {
    zend_throw_exception(fu_ex_unsupported, "UUIDv7 monotonic timestamp exhausted", 0);
    return FAILURE;
}

static zend_always_inline zend_result fu_v7_increment(uint64_t *key, uint64_t *randb) {
    unsigned char r[3];
    if (fu_rand(r, sizeof(r)) == FAILURE) return FAILURE;
    uint64_t step = ((uint64_t)r[0] << 16 | (uint64_t)r[1] << 8 | r[2]) + 1;
    uint64_t next = *randb + step; /* 1..2^24, so the 62-bit addition fits uint64_t */
    if (UNEXPECTED(next > FU_RANDB_MASK)) {
        if (UNEXPECTED(*key == ((1ULL << 60) - 1))) {
            return fu_v7_exhausted();
        }
        (*key)++;
        next &= FU_RANDB_MASK;
    }
    *randb = next;
    return SUCCESS;
}

static zend_always_inline zend_result fu_v7_start(uint64_t *key, uint64_t *randb) {
    if (!FAST_UUID_G(v7_initialized) || *key > FAST_UUID_G(v7_key)) {
        if (fu_rand_u64(randb) == FAILURE) return FAILURE;
        *randb &= FU_RANDB_MASK;
    } else {
        *key = FAST_UUID_G(v7_key);
        *randb = FAST_UUID_G(v7_randb);
        if (fu_v7_increment(key, randb) == FAILURE) return FAILURE;
    }
    return SUCCESS;
}

/* monotonic v7 using module-global state. The 60-bit time key (ms<<12 | sub)
   gives ~244ns resolution; rand_b advances by random positive steps on ties. */
static zend_always_inline zend_result fu_gen_v7(unsigned char *b) {
    uint64_t key;
    if (fu_v7_now_key(&key) == FAILURE) return FAILURE;
    uint64_t randb;
    if (fu_v7_start(&key, &randb) == FAILURE) return FAILURE;
    FAST_UUID_G(v7_key) = key;
    FAST_UUID_G(v7_randb) = randb;
    FAST_UUID_G(v7_initialized) = 1;
    fu_lay_v7_key(b, key, randb);
    return SUCCESS;
}

/* non-monotonic v7 at an explicit timestamp (ms + 12-bit sub-ms fraction) */
static zend_always_inline zend_result fu_gen_v7_at(unsigned char *b, uint64_t ms, uint64_t sub) {
    uint64_t randb;
    if (fu_rand_u64(&randb) == FAILURE) return FAILURE;
    randb &= FU_RANDB_MASK;
    fu_lay_v7_key(b, (ms << 12) | sub, randb);
    return SUCCESS;
}

static zend_always_inline int fu_name_len_ok(size_t nl) {
    if (UNEXPECTED(nl > FU_MAX_NAME_BYTES)) {
        zend_throw_exception_ex(fu_ex_invalid_arg, 0,
            "name is too large (max %u bytes)", FU_MAX_NAME_BYTES);
        return 0;
    }
    return 1;
}

static void fu_gen_v3(unsigned char *b, const unsigned char ns[16], const char *name, size_t nl) {
    if (!fu_name_len_ok(nl)) return;
#ifndef WORDS_BIGENDIAN
    fu_md5_v3(b, ns, name, nl);
#else
    PHP_MD5_CTX c; unsigned char d[16];
    PHP_MD5Init(&c);
    PHP_MD5Update(&c, ns, 16);
    PHP_MD5Update(&c, (const unsigned char *)name, nl);
    PHP_MD5Final(d, &c);
    memcpy(b, d, 16);
    fu_set_ver_var(b, 0x30);
#endif
}

static void fu_gen_v5(unsigned char *b, const unsigned char ns[16], const char *name, size_t nl) {
    if (!fu_name_len_ok(nl)) return;
#ifdef FU_HAVE_SHA1_HW
    if (EXPECTED(fu_sha1_active)) {
        fu_sha1_v5_hw(b, ns, name, nl);
        return;
    }
#endif
    PHP_SHA1_CTX c; unsigned char d[20];
    PHP_SHA1Init(&c);
    PHP_SHA1Update(&c, ns, 16);
    PHP_SHA1Update(&c, (const unsigned char *)name, nl);
    PHP_SHA1Final(d, &c);
    memcpy(b, d, 16);
    fu_set_ver_var(b, 0x50);
}

static zend_always_inline void fu_gen_v8(unsigned char *b, const unsigned char *data) {
    memcpy(b, data, 16);
    fu_set_ver_var(b, 0x80);
}

/* ------------------------------------------------------------------ */
/* big-integer helpers (cold path)                                    */
/* ------------------------------------------------------------------ */

static zend_string *fu_to_decimal(const unsigned char *b) {
    unsigned char t[16]; memcpy(t, b, 16);
    char buf[40]; int pos = 40, nz;
    do {
        int rem = 0; nz = 0;
        for (int i = 0; i < 16; i++) {
            int cur = (rem << 8) | t[i];
            t[i] = cur / 10; rem = cur % 10;
            if (t[i]) nz = 1;
        }
        buf[--pos] = '0' + rem;
    } while (nz);
    return zend_string_init(buf + pos, 40 - pos, 0);
}

static int fu_from_decimal(const char *s, size_t len, unsigned char out[16]) {
    if (!len) return 0;
    if ((len > 1 && s[0] == '0') || len > 39) return 0;
    memset(out, 0, 16);
    for (size_t i = 0; i < len; i++) {
        if (s[i] < '0' || s[i] > '9') return 0;
        int carry = s[i] - '0';
        for (int j = 15; j >= 0; j--) { int cur = out[j]*10 + carry; out[j] = cur & 0xff; carry = cur >> 8; }
        if (carry) return 0; /* > 128 bits */
    }
    return 1;
}

/* ------------------------------------------------------------------ */
/* shared helpers                                                     */
/* ------------------------------------------------------------------ */

static zend_always_inline void fu_return_uuid(zval *rv, const unsigned char b[16]) {
    if (UNEXPECTED(EG(exception))) return; /* CSPRNG failure during generation */
    fu_obj *u = fu_alloc_obj();
    memcpy(u->b, b, 16);
    ZVAL_OBJ(rv, &u->std);
}

/* Resolve a UUID-valued argument (namespace, equals, compareTo) to 16 bytes.
   Accepts a native Uuid, a compat/userland wrapper via getCore(), any other
   Stringable by parsing its string form, or a UUID string. getCore() preserves
   native identity under presentation codecs. Foreign getBytes() may use GUID
   or ordered-time storage order, so only the canonical string form is trusted.
   Returns 0 on a non-match or unparseable value; on a throwing accessor it
   leaves EG(exception) set, which the caller must honor rather than
   overwrite. */
#if PHP_VERSION_ID >= 80300
# define FU_TYPE_HAS_LITERAL_NAME(t) ZEND_TYPE_HAS_LITERAL_NAME(t)
#else
# define FU_TYPE_HAS_LITERAL_NAME(t) 0
#endif

static int fu_type_may_return(
    zend_type type, zend_class_entry *scope, uint32_t allowed_types, zend_class_entry *required_class) {
    if (ZEND_TYPE_PURE_MASK(type) & allowed_types) return 1;
    if (ZEND_TYPE_HAS_LIST(type)) {
        const zend_type *part;
        ZEND_TYPE_FOREACH(type, part) {
            int compatible = fu_type_may_return(*part, scope, allowed_types, required_class);
            if (ZEND_TYPE_IS_INTERSECTION(type)) {
                if (!compatible) return 0;
            } else if (compatible) {
                return 1;
            }
        } ZEND_TYPE_FOREACH_END();
        return ZEND_TYPE_IS_INTERSECTION(type);
    }
    if (!ZEND_TYPE_HAS_NAME(type) && !FU_TYPE_HAS_LITERAL_NAME(type)) {
        return 0;
    }
    if (!required_class) return 0;

    zend_string *name = ZEND_TYPE_HAS_NAME(type) ? ZEND_TYPE_NAME(type) : NULL;
    const char *name_str = name ? ZSTR_VAL(name) : ZEND_TYPE_LITERAL_NAME(type);
    size_t name_len = name ? ZSTR_LEN(name) : strlen(name_str);
    if (name_len == ZSTR_LEN(required_class->name)
        && zend_binary_strcasecmp(
            name_str, name_len, ZSTR_VAL(required_class->name), ZSTR_LEN(required_class->name)) == 0) {
        return 1;
    }
    zend_class_entry *candidate = NULL;
    if (name && (zend_string_equals_literal(name, "self") || zend_string_equals_literal(name, "static"))) {
        candidate = scope;
    } else if (name && zend_string_equals_literal(name, "parent")) {
        candidate = scope ? scope->parent : NULL;
    } else {
        /* NO_AUTOLOAD: a type name must never trigger the autoloader from here */
        zend_string *lookup = name ? zend_string_copy(name) : zend_string_init(name_str, name_len, 0);
        candidate = zend_lookup_class_ex(lookup, NULL, ZEND_FETCH_CLASS_NO_AUTOLOAD);
        zend_string_release(lookup);
    }
    return candidate && (instanceof_function(required_class, candidate)
        || instanceof_function(candidate, required_class));
}
#undef FU_TYPE_HAS_LITERAL_NAME

static int fu_method_may_return(
    const zend_function *method, uint32_t allowed_types, zend_class_entry *required_class) {
    if (!(method->common.fn_flags & ZEND_ACC_HAS_RETURN_TYPE)) return 1;
    return fu_type_may_return(
        method->common.arg_info[-1].type, method->common.scope, allowed_types, required_class);
}

static zend_function *fu_find_zero_arg_method(
    zend_class_entry *ce, zend_string *name, uint32_t allowed_return_types,
    zend_class_entry *required_class) {
    zend_function *method = zend_hash_find_ptr(&ce->function_table, name);
    if (method != NULL
        && method->common.required_num_args == 0
        && (method->common.fn_flags & (ZEND_ACC_PUBLIC | ZEND_ACC_ABSTRACT | ZEND_ACC_STATIC)) == ZEND_ACC_PUBLIC
        && fu_method_may_return(method, allowed_return_types, required_class)) {
        return method;
    }
    return NULL;
}

static int fu_resolve_uuid(zval *z, unsigned char ns[16]) {
    if (Z_TYPE_P(z) == IS_OBJECT) {
        zend_class_entry *ce = Z_OBJCE_P(z);
        if (EXPECTED(ce == fast_uuid_ce)) {
            memcpy(ns, fu_from_zobj(Z_OBJ_P(z))->b, 16);
            return 1;
        }
        zend_function *fn = fu_find_zero_arg_method(
            ce, fu_str_getcore, MAY_BE_OBJECT, fast_uuid_ce);
        if (fn) {
            zval ret;
            ZVAL_UNDEF(&ret);
            zend_call_known_instance_method(fn, Z_OBJ_P(z), &ret, 0, NULL);
            if (!EG(exception)
                && Z_TYPE(ret) == IS_OBJECT && Z_OBJCE(ret) == fast_uuid_ce) {
                memcpy(ns, fu_from_zobj(Z_OBJ(ret))->b, 16);
                zval_ptr_dtor(&ret);
                return 1;
            }
            if (Z_TYPE(ret) != IS_UNDEF) zval_ptr_dtor(&ret);
            if (EG(exception)) return 0;
        }
        if (instanceof_function(ce, zend_ce_stringable)) {
            zval t; ZVAL_OBJ(&t, Z_OBJ_P(z));
            zend_string *s = zval_get_string(&t);
            if (EG(exception)) { if (s) zend_string_release(s); return 0; }
            int ok = fu_parse(ZSTR_VAL(s), ZSTR_LEN(s), ns);
            zend_string_release(s);
            return ok;
        }
        return 0;
    }
    if (Z_TYPE_P(z) == IS_STRING) return fu_parse(Z_STRVAL_P(z), Z_STRLEN_P(z), ns);
    return 0;
}

/* parse a node override: 12-hex string, or int in 0..0xffffffffffff. returns 1 on success */
static int fu_parse_node(zend_string *str, zend_long lv, unsigned char node[6]) {
    if (!str) {
        if (lv < 0 || (uint64_t)lv > FU_MS48_MAX) return 0; /* reject, don't truncate */
        uint64_t n = (uint64_t)lv;
        for (int i = 0; i < 6; i++) node[i] = (n >> (8 * (5 - i))) & 0xff;
        return 1;
    }
    const char *s = ZSTR_VAL(str);
    if (ZSTR_LEN(str) != 12) return 0;
    for (int i = 0; i < 6; i++) {
        int hi = fu_nib_lut[(unsigned char)s[i*2]];
        int lo = fu_nib_lut[(unsigned char)s[i*2+1]];
        if ((hi | lo) < 0) return 0;
        node[i] = (hi << 4) | lo;
    }
    return 1;
}

/* Parse an optional node argument (12-hex string or int) into a 6-byte buffer.
   Sets *out to the buffer when a non-null node was given, else NULL. Throws and
   returns FAILURE on a present-but-invalid node. */
static zend_result fu_node_arg(zend_string *node_str, zend_long node_long, zend_bool node_null,
                               unsigned char node[6], const unsigned char **out) {
    if (node_null) {
        *out = NULL;
        return SUCCESS;
    }
    if (!fu_parse_node(node_str, node_long, node)) {
        zend_throw_exception(fu_ex_invalid_arg, "Invalid node (expect 12-hex string or int)", 0);
        return FAILURE;
    }
    *out = node;
    return SUCCESS;
}

/* Validate an optional clock sequence. cs_null means "not supplied" -> -1 (random).
   A supplied value must be 0..0x3fff; out-of-range throws rather than truncating. */
static zend_result fu_clockseq_arg(zend_long clockseq, zend_bool cs_null, int *out) {
    if (cs_null) { *out = -1; return SUCCESS; }
    if (clockseq < 0 || clockseq > 0x3fff) {
        zend_throw_exception(fu_ex_invalid_arg, "clockSeq out of range (0..16383)", 0);
        return FAILURE;
    }
    *out = (int)clockseq;
    return SUCCESS;
}

static zend_result fu_local_id_arg(zend_long local_domain, zend_string *id_str,
                                   zend_long id_long, zend_bool id_null, uint32_t *out) {
    if (id_null) {
        if (local_domain != 0 && local_domain != 1) {
            zend_throw_exception(fu_ex_invalid_arg,
                "localIdentifier is required for DCE domains other than PERSON (0) and GROUP (1)", 0);
            return FAILURE;
        }
#ifndef PHP_WIN32
        *out = (local_domain == 1) ? (uint32_t)getgid() : (uint32_t)getuid();
        return SUCCESS;
#else
        /* No POSIX uid/gid: a silent 0 would be indistinguishable from root. */
        zend_throw_exception(fu_ex_invalid_arg,
            "localIdentifier is required on Windows (no process uid/gid to fall back to)", 0);
        return FAILURE;
#endif
    }

    if (!id_str) {
        if (id_long < 0 || (uint64_t)id_long > 0xFFFFFFFFULL) {
            zend_throw_exception(fu_ex_invalid_arg, "localIdentifier out of range (0..4294967295)", 0);
            return FAILURE;
        }
        *out = (uint32_t)id_long;
        return SUCCESS;
    }

    const char *sv = ZSTR_VAL(id_str);
    size_t sl = ZSTR_LEN(id_str);
    if (sl == 0) {
        zend_throw_exception(fu_ex_invalid_arg, "localIdentifier string must be a decimal number", 0);
        return FAILURE;
    }
    if (sl > 1 && sv[0] == '0') {
        zend_throw_exception(fu_ex_invalid_arg, "localIdentifier string must be a canonical decimal number", 0);
        return FAILURE;
    }
    if (sl > 10) {
        zend_throw_exception(fu_ex_invalid_arg, "localIdentifier out of range (0..4294967295)", 0);
        return FAILURE;
    }
    for (size_t i = 0; i < sl; i++) {
        if (sv[i] < '0' || sv[i] > '9') {
            zend_throw_exception(fu_ex_invalid_arg, "localIdentifier string must be a decimal number", 0);
            return FAILURE;
        }
    }

    errno = 0;
    char *end = NULL;
    unsigned long long value = strtoull(sv, &end, 10);
    if (errno == ERANGE || end != sv + sl || value > 0xFFFFFFFFULL) {
        zend_throw_exception(fu_ex_invalid_arg, "localIdentifier out of range (0..4294967295)", 0);
        return FAILURE;
    }
    *out = (uint32_t)value;
    return SUCCESS;
}

/* Read whole seconds + sub-second microseconds off a DateTimeInterface. Throws
   (returns FAILURE) if either call fails or raises. */
static zend_result fu_dt_secs_micros(zend_object *dtobj, int64_t *secs, uint32_t *micros) {
    /* Only exact built-in classes can bypass overridable methods. A stale or
       missing timelib cache must fall back: timelib_update_ts is not exported. */
    if (dtobj->ce == php_date_get_immutable_ce() || dtobj->ce == php_date_get_date_ce()) {
        php_date_obj *dobj = php_date_obj_from_obj(dtobj);
        if (dobj->time && dobj->time->sse_uptodate) {
            *secs = (int64_t)dobj->time->sse;
            *micros = (uint32_t)dobj->time->us;
            return SUCCESS;
        }
    }
    zval zdt; ZVAL_OBJ(&zdt, dtobj);
    zval fn, ret;
    ZVAL_STRING(&fn, "getTimestamp");
    ZVAL_UNDEF(&ret);
    zend_result cr = call_user_function(NULL, &zdt, &fn, &ret, 0, NULL);
    zval_ptr_dtor(&fn);
    if (cr != SUCCESS || EG(exception)) {
        if (Z_TYPE(ret) != IS_UNDEF) zval_ptr_dtor(&ret);
        if (!EG(exception)) {
            zend_throw_exception(fu_ex_unsupported, "DateTime getTimestamp() failed", 0);
        }
        return FAILURE;
    }
    if (Z_TYPE(ret) != IS_LONG) {
        zval_ptr_dtor(&ret);
        zend_throw_exception(fu_ex_invalid_arg, "DateTime getTimestamp() must return an int", 0);
        return FAILURE;
    }
    *secs = (int64_t)Z_LVAL(ret);
    zval afmt; ZVAL_STRING(&fn, "format"); ZVAL_STRING(&afmt, "u");
    ZVAL_UNDEF(&ret);
    cr = call_user_function(NULL, &zdt, &fn, &ret, 1, &afmt);
    zval_ptr_dtor(&fn); zval_ptr_dtor(&afmt);
    if (cr != SUCCESS || EG(exception)) {
        if (Z_TYPE(ret) != IS_UNDEF) zval_ptr_dtor(&ret);
        if (!EG(exception)) {
            zend_throw_exception(fu_ex_unsupported, "DateTime format(\"u\") failed", 0);
        }
        return FAILURE;
    }
    /* Subclasses can override format("u"); require six digits to prevent
       coercion or carry into the seconds field. */
    if (Z_TYPE(ret) != IS_STRING || Z_STRLEN(ret) != 6) {
        zval_ptr_dtor(&ret);
        zend_throw_exception(fu_ex_invalid_arg, "DateTime format(\"u\") must return six decimal digits", 0);
        return FAILURE;
    }
    const char *mv = Z_STRVAL(ret);
    uint32_t m = 0;
    for (int i = 0; i < 6; i++) {
        if (mv[i] < '0' || mv[i] > '9') {
            zval_ptr_dtor(&ret);
            zend_throw_exception(fu_ex_invalid_arg, "DateTime format(\"u\") must return six decimal digits", 0);
            return FAILURE;
        }
        m = m * 10 + (uint32_t)(mv[i] - '0');
    }
    zval_ptr_dtor(&ret);
    *micros = m; /* 0..999999 by construction */
    return SUCCESS;
}

static zend_result fu_make_datetime_from_string(zval *rv, int64_t secs, uint32_t micros) {
    zend_string *buf = strpprintf(0, "%lld.%06u", (long long)secs, micros);
    zval callable, args[2], ret;
    array_init(&callable);
    add_next_index_string(&callable, "DateTimeImmutable");
    add_next_index_string(&callable, "createFromFormat");
    ZVAL_STRING(&args[0], "U.u");
    ZVAL_STR(&args[1], buf); /* transfers buf ref */
    zend_result ok = FAILURE;
    ZVAL_UNDEF(&ret);
    if (call_user_function(NULL, NULL, &callable, &ret, 2, args) == SUCCESS && Z_TYPE(ret) == IS_OBJECT) {
        ZVAL_COPY_VALUE(rv, &ret);
        ok = SUCCESS;
    } else if (Z_TYPE(ret) != IS_UNDEF) {
        zval_ptr_dtor(&ret);
    }
    zval_ptr_dtor(&callable);
    zval_ptr_dtor(&args[0]);
    zval_ptr_dtor(&args[1]);
    if (ok != SUCCESS && !EG(exception)) {
        zend_throw_exception(fu_ex_unsupported, "Could not build DateTimeImmutable from UUID timestamp", 0);
    }
    return ok;
}

/* build a DateTimeImmutable (UTC) from whole seconds + microseconds, preserving
   sub-second. 8.4+ uses ext/date's internal constructor (PHPAPI) directly when
   the seconds fit zend_long; older versions and 32-bit overflow cases fall back
   to DateTimeImmutable::createFromFormat("U.u"). */
static zend_result fu_make_datetime(zval *rv, int64_t secs, uint32_t micros) {
#if PHP_VERSION_ID >= 80400
    if (secs >= (int64_t) ZEND_LONG_MIN && secs <= (int64_t) ZEND_LONG_MAX) {
        zval tmp;
        ZVAL_UNDEF(&tmp);
        php_date_instantiate(php_date_get_immutable_ce(), &tmp);
        /* php_date_initialize_from_ts_long() returns void: timelib may still
           saturate on extreme inputs (v1 spans 1582..5236), so verify the
           built time round-trips and fall through to the throwing string
           path otherwise. */
        if (!EG(exception) && Z_TYPE(tmp) == IS_OBJECT) {
            php_date_obj *dobj = php_date_obj_from_obj(Z_OBJ_P(&tmp));
            php_date_initialize_from_ts_long(dobj, (zend_long)secs, (int)micros);
            if (dobj->time && (int64_t)dobj->time->sse == secs
                && (int64_t)dobj->time->us == (int64_t)micros) {
                ZVAL_COPY_VALUE(rv, &tmp);
                return SUCCESS;
            }
        }
        if (Z_TYPE(tmp) != IS_UNDEF) zval_ptr_dtor(&tmp);
    }
#endif
    return fu_make_datetime_from_string(rv, secs, micros);
}

/* ------------------------------------------------------------------ */
/* static factories                                                   */
/* ------------------------------------------------------------------ */

PHP_METHOD(FastUuid_Uuid, uuid1) {
    zend_string *node_str = NULL; zend_long node_long = 0, clockseq = -1;
    zend_bool node_null = 1, cs_null = 1;
    ZEND_PARSE_PARAMETERS_START(0, 2)
        Z_PARAM_OPTIONAL
        Z_PARAM_STR_OR_LONG_OR_NULL(node_str, node_long, node_null)
        Z_PARAM_LONG_OR_NULL(clockseq, cs_null)
    ZEND_PARSE_PARAMETERS_END();
    unsigned char node[6]; const unsigned char *np;
    if (fu_node_arg(node_str, node_long, node_null, node, &np) == FAILURE) RETURN_THROWS();
    int cseq = -1; if (fu_clockseq_arg(clockseq, cs_null, &cseq) == FAILURE) RETURN_THROWS();
    unsigned char b[16]; if (fu_gen_v1_ex(b, np, cseq) == FAILURE) RETURN_THROWS(); fu_return_uuid(return_value, b);
}

PHP_METHOD(FastUuid_Uuid, uuid2) {
    zend_long local_domain;
    zend_string *id_str = NULL, *node_str = NULL;
    zend_long id_long = 0, node_long = 0, clockseq = -1;
    zend_bool id_null = 1, node_null = 1, cs_null = 1;
    ZEND_PARSE_PARAMETERS_START(1, 4)
        Z_PARAM_LONG(local_domain)
        Z_PARAM_OPTIONAL
        Z_PARAM_STR_OR_LONG_OR_NULL(id_str, id_long, id_null)
        Z_PARAM_STR_OR_LONG_OR_NULL(node_str, node_long, node_null)
        Z_PARAM_LONG_OR_NULL(clockseq, cs_null)
    ZEND_PARSE_PARAMETERS_END();
    if (local_domain < 0 || local_domain > 0xff) {
        zend_throw_exception(fu_ex_invalid_arg, "localDomain must be 0..255 (PERSON=0, GROUP=1, ORG=2)", 0);
        RETURN_THROWS();
    }
    uint32_t local_id;
    if (fu_local_id_arg(local_domain, id_str, id_long, id_null, &local_id) == FAILURE) RETURN_THROWS();
    unsigned char node[6]; const unsigned char *np;
    if (fu_node_arg(node_str, node_long, node_null, node, &np) == FAILURE) RETURN_THROWS();
    int cseq = -1; if (fu_clockseq_arg(clockseq, cs_null, &cseq) == FAILURE) RETURN_THROWS();
    unsigned char b[16];
    /* Apply the high-water mark only to a fully supplied node+clockseq pair;
       defaulted values retain the randomized path without the mark or its drift. */
    zend_bool mono = (!node_null && !cs_null);
    if (fu_gen_v2(b, local_id, (unsigned char)local_domain, np, cseq, mono) == FAILURE) RETURN_THROWS();
    fu_return_uuid(return_value, b);
}

PHP_METHOD(FastUuid_Uuid, uuid3) {
    zend_object *ns_obj; zend_string *ns_str, *name;
    ZEND_PARSE_PARAMETERS_START(2, 2) Z_PARAM_OBJ_OR_STR(ns_obj, ns_str) Z_PARAM_STR(name) ZEND_PARSE_PARAMETERS_END();
    zval zns;
    if (ns_obj) { ZVAL_OBJ(&zns, ns_obj); } else { ZVAL_STR(&zns, ns_str); }
    unsigned char ns[16];
    if (!fu_resolve_uuid(&zns, ns)) { if (!EG(exception)) zend_throw_exception(fu_ex_invalid_arg, "Invalid namespace", 0); RETURN_THROWS(); }
    unsigned char b[16];
    fu_gen_v3(b, ns, ZSTR_VAL(name), ZSTR_LEN(name));
    if (UNEXPECTED(EG(exception))) RETURN_THROWS();
    fu_return_uuid(return_value, b);
}

PHP_METHOD(FastUuid_Uuid, uuid4) {
    ZEND_PARSE_PARAMETERS_NONE();
    unsigned char b[16]; if (fu_gen_v4(b) == FAILURE) RETURN_THROWS(); fu_return_uuid(return_value, b);
}

PHP_METHOD(FastUuid_Uuid, uuid5) {
    zend_object *ns_obj; zend_string *ns_str, *name;
    ZEND_PARSE_PARAMETERS_START(2, 2) Z_PARAM_OBJ_OR_STR(ns_obj, ns_str) Z_PARAM_STR(name) ZEND_PARSE_PARAMETERS_END();
    zval zns;
    if (ns_obj) { ZVAL_OBJ(&zns, ns_obj); } else { ZVAL_STR(&zns, ns_str); }
    unsigned char ns[16];
    if (!fu_resolve_uuid(&zns, ns)) { if (!EG(exception)) zend_throw_exception(fu_ex_invalid_arg, "Invalid namespace", 0); RETURN_THROWS(); }
    unsigned char b[16];
    fu_gen_v5(b, ns, ZSTR_VAL(name), ZSTR_LEN(name));
    if (UNEXPECTED(EG(exception))) RETURN_THROWS();
    fu_return_uuid(return_value, b);
}

PHP_METHOD(FastUuid_Uuid, uuid6) {
    zend_string *node_str = NULL; zend_long node_long = 0, clockseq = -1;
    zend_bool node_null = 1, cs_null = 1;
    ZEND_PARSE_PARAMETERS_START(0, 2)
        Z_PARAM_OPTIONAL
        Z_PARAM_STR_OR_LONG_OR_NULL(node_str, node_long, node_null)
        Z_PARAM_LONG_OR_NULL(clockseq, cs_null)
    ZEND_PARSE_PARAMETERS_END();
    unsigned char node[6]; const unsigned char *np;
    if (fu_node_arg(node_str, node_long, node_null, node, &np) == FAILURE) RETURN_THROWS();
    int cseq = -1; if (fu_clockseq_arg(clockseq, cs_null, &cseq) == FAILURE) RETURN_THROWS();
    unsigned char b[16]; if (fu_gen_v6_ex(b, np, cseq) == FAILURE) RETURN_THROWS(); fu_return_uuid(return_value, b);
}

/* non-monotonic v7 from a unix-millisecond integer (sub-ms fraction = 0) */
static zend_result fu_v7_at_ms(unsigned char *b, zend_long ms) {
    if (ms < 0 || (uint64_t)ms > FU_MS48_MAX) {
        zend_throw_exception(fu_ex_invalid_arg, "v7 millisecond timestamp out of range (0 .. 281474976710655)", 0);
        return FAILURE;
    }
    return fu_gen_v7_at(b, (uint64_t)ms, 0);
}

static zend_result fu_v7_at_datetime(unsigned char *b, zend_object *dtobj) {
    int64_t sec;
    uint32_t micros;
    if (fu_dt_secs_micros(dtobj, &sec, &micros) == FAILURE) return FAILURE;
    if (sec < 0) { /* v7 timestamps are unsigned ms since the unix epoch */
        zend_throw_exception(fu_ex_invalid_arg, "uuid7 does not support dates before 1970-01-01", 0);
        return FAILURE;
    }
    if (sec > 281474976710LL) { /* sec*1000 must fit the 48-bit ms field (max ~year 10889) */
        zend_throw_exception(fu_ex_invalid_arg, "uuid7 timestamp out of range (max ~year 10889)", 0);
        return FAILURE;
    }
    uint64_t ms = (uint64_t)sec * 1000ULL + micros / 1000ULL;
    if (ms > FU_MS48_MAX) { /* precise 48-bit ceiling (boundary second + sub-ms) */
        zend_throw_exception(fu_ex_invalid_arg, "uuid7 timestamp out of range (max ~year 10889)", 0);
        return FAILURE;
    }
    uint64_t sub = ((uint64_t)(micros % 1000) * 4096ULL) / 1000ULL; /* sub-ms us -> 12-bit */
    return fu_gen_v7_at(b, ms, sub);
}

PHP_METHOD(FastUuid_Uuid, uuid7) {
    zval *when = NULL;
    ZEND_PARSE_PARAMETERS_START(0, 1)
        Z_PARAM_OPTIONAL
        Z_PARAM_ZVAL_OR_NULL(when)
    ZEND_PARSE_PARAMETERS_END();
    unsigned char b[16];
    zend_result result;
    if (when == NULL || Z_TYPE_P(when) == IS_NULL) {
        result = fu_gen_v7(b);
    } else if (Z_TYPE_P(when) == IS_LONG) {
        result = fu_v7_at_ms(b, Z_LVAL_P(when));
    } else if (Z_TYPE_P(when) == IS_OBJECT &&
               instanceof_function(Z_OBJ_P(when)->ce, php_date_get_interface_ce())) {
        result = fu_v7_at_datetime(b, Z_OBJ_P(when));
    } else {
        zend_argument_type_error(1, "must be of type int|DateTimeInterface|null, %s given",
#if PHP_VERSION_ID >= 80300
                                 zend_zval_value_name(when));
#else
                                 zend_zval_type_name(when));
#endif
        RETURN_THROWS();
    }
    if (result == FAILURE) RETURN_THROWS();
    fu_return_uuid(return_value, b);
}

PHP_METHOD(FastUuid_Uuid, uuid8) {
    zend_string *data;
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_STR(data) ZEND_PARSE_PARAMETERS_END();
    if (UNEXPECTED(ZSTR_LEN(data) != 16)) { zend_throw_exception(fu_ex_invalid_arg, "uuid8 requires 16 bytes", 0); RETURN_THROWS(); }
    fu_obj *u = fu_alloc_obj();
    fu_gen_v8(u->b, (const unsigned char *)ZSTR_VAL(data));
    RETURN_OBJ(&u->std);
}

PHP_METHOD(FastUuid_Uuid, fromString) {
    zend_string *s;
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_STR(s) ZEND_PARSE_PARAMETERS_END();
    unsigned char b[16];
    if (UNEXPECTED(!fu_parse(ZSTR_VAL(s), ZSTR_LEN(s), b))) {
        zend_throw_exception_ex(fu_ex_invalid_str, 0, "Invalid UUID string (len=%zu)", ZSTR_LEN(s));
        RETURN_THROWS();
    }
    fu_return_uuid(return_value, b);
}

PHP_METHOD(FastUuid_Uuid, fromBytes) {
    zend_string *s;
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_STR(s) ZEND_PARSE_PARAMETERS_END();
    if (UNEXPECTED(ZSTR_LEN(s) != 16)) { zend_throw_exception(fu_ex_invalid_arg, "UUID bytes must be 16 long", 0); RETURN_THROWS(); }
    fu_obj *u = fu_alloc_obj();
    memcpy(u->b, ZSTR_VAL(s), 16);
    RETURN_OBJ(&u->std);
}

PHP_METHOD(FastUuid_Uuid, fromInteger) {
    zend_string *s;
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_STR(s) ZEND_PARSE_PARAMETERS_END();
    unsigned char b[16];
    if (!fu_from_decimal(ZSTR_VAL(s), ZSTR_LEN(s), b)) { zend_throw_exception(fu_ex_invalid_arg, "Invalid integer", 0); RETURN_THROWS(); }
    fu_return_uuid(return_value, b);
}

PHP_METHOD(FastUuid_Uuid, isValid) {
    zend_string *s;
    bool strict = false;
    ZEND_PARSE_PARAMETERS_START(1, 2) Z_PARAM_STR(s) Z_PARAM_OPTIONAL Z_PARAM_BOOL(strict) ZEND_PARSE_PARAMETERS_END();
    if (strict) RETURN_BOOL(fu_valid_strict(ZSTR_VAL(s), ZSTR_LEN(s)));
    RETURN_BOOL(fu_valid_tolerant(ZSTR_VAL(s), ZSTR_LEN(s)));
}

PHP_METHOD(FastUuid_Uuid, fromHexadecimal) {
    zend_object *obj = NULL; zend_string *s = NULL;
    bool pad = false;
    ZEND_PARSE_PARAMETERS_START(1, 2)
        Z_PARAM_OBJ_OF_CLASS_OR_STR(obj, zend_ce_stringable, s)
        Z_PARAM_OPTIONAL
        Z_PARAM_BOOL(pad)
    ZEND_PARSE_PARAMETERS_END();
    /* accept a 32-char hex string or any object stringifiable to one (ramsey Type\Hexadecimal) */
    bool release_s = false;
    if (obj) {
        zval t; ZVAL_OBJ(&t, obj); s = zval_get_string(&t);
        if (EG(exception)) { if (s) zend_string_release(s); RETURN_THROWS(); } /* propagate a throwing __toString */
        release_s = true;
    }
    unsigned char b[16];
    size_t len = ZSTR_LEN(s);
    int ok;
    if (pad && len >= 1 && len < 32) {
        char padded[32];
        memset(padded, '0', 32 - len);
        memcpy(padded + 32 - len, ZSTR_VAL(s), len);
        ok = fu_parse_hex32(padded, b);
    } else {
        ok = len == 32 && fu_parse_hex32(ZSTR_VAL(s), b);
    }
    if (release_s) zend_string_release(s);
    if (UNEXPECTED(!ok)) {
        zend_throw_exception(fu_ex_invalid_str, pad ? "Invalid hexadecimal UUID (expect 1 to 32 hex chars)" : "Invalid hexadecimal UUID (expect 32 hex chars)", 0);
        RETURN_THROWS();
    }
    fu_return_uuid(return_value, b);
}

PHP_METHOD(FastUuid_Uuid, fromDateTime) {
    zend_object *dtobj; zend_string *node_str = NULL;
    zend_long node_long = 0, clockseq = -1; zend_bool node_null = 1, cs_null = 1;
    ZEND_PARSE_PARAMETERS_START(1, 3)
        Z_PARAM_OBJ_OF_CLASS(dtobj, php_date_get_interface_ce())
        Z_PARAM_OPTIONAL
        Z_PARAM_STR_OR_LONG_OR_NULL(node_str, node_long, node_null)
        Z_PARAM_LONG_OR_NULL(clockseq, cs_null)
    ZEND_PARSE_PARAMETERS_END();
    int64_t secs; uint32_t micros;
    if (fu_dt_secs_micros(dtobj, &secs, &micros) == FAILURE) RETURN_THROWS();

    unsigned char node[6]; const unsigned char *np;
    if (fu_node_arg(node_str, node_long, node_null, node, &np) == FAILURE) RETURN_THROWS();
    int cseq = -1; if (fu_clockseq_arg(clockseq, cs_null, &cseq) == FAILURE) RETURN_THROWS();
    /* The v1 timestamp is a 60-bit count of 100ns ticks since 1582-10-15. Bound
       secs first so the multiply can't overflow int64, then range-check the
       Gregorian tick value precisely (valid window ~1582-10-15 .. ~year 5236).
       signed: dates before 1970 give a negative unix-seconds value. */
    if (secs < -200000000000LL || secs > 200000000000LL) {
        zend_throw_exception(fu_ex_invalid_arg, "DateTime out of range for a v1 UUID timestamp", 0);
        RETURN_THROWS();
    }
    int64_t g = secs * 10000000LL + (int64_t)micros * 10LL + (int64_t)FU_GREG_UNIX_EPOCH;
    if (g < 0 || (uint64_t)g > ((1ULL << 60) - 1)) {
        zend_throw_exception(fu_ex_invalid_arg, "DateTime out of range for a v1 UUID timestamp (1582-10-15 .. ~5236)", 0);
        RETURN_THROWS();
    }
    unsigned char b[16]; if (fu_lay_v1(b, (uint64_t)g, np, cseq) == FAILURE) RETURN_THROWS();
    fu_return_uuid(return_value, b);
}

/* ------------------------------------------------------------------ */
/* instance methods                                                   */
/* ------------------------------------------------------------------ */

PHP_METHOD(FastUuid_Uuid, __construct) {
    zend_throw_exception(fu_ex_invalid_arg, "Use FastUuid\\Uuid::uuid* / from* factories", 0);
    RETURN_THROWS();
}

PHP_METHOD(FastUuid_Uuid, toString) {
    ZEND_PARSE_PARAMETERS_NONE();
    RETURN_STR(fu_str(fu_from_zobj(Z_OBJ_P(getThis()))));
}

PHP_METHOD(FastUuid_Uuid, getBytes) {
    ZEND_PARSE_PARAMETERS_NONE();
    fu_obj *u = fu_from_zobj(Z_OBJ_P(getThis()));
    RETURN_STRINGL((char *)u->b, 16);
}

PHP_METHOD(FastUuid_Uuid, getHex) {
    ZEND_PARSE_PARAMETERS_NONE();
    fu_obj *u = fu_from_zobj(Z_OBJ_P(getThis()));
    zend_string *s = zend_string_alloc(32, 0);
    fu_hex32(u->b, ZSTR_VAL(s)); ZSTR_VAL(s)[32] = '\0';
    RETURN_STR(s);
}

PHP_METHOD(FastUuid_Uuid, getUrn) {
    ZEND_PARSE_PARAMETERS_NONE();
    fu_obj *u = fu_from_zobj(Z_OBJ_P(getThis()));
    zend_string *s = zend_string_alloc(45, 0);
    memcpy(ZSTR_VAL(s), "urn:uuid:", 9);
    if (u->str) {
        memcpy(ZSTR_VAL(s) + 9, ZSTR_VAL(u->str), 36);
    } else {
        fu_format36(u->b, ZSTR_VAL(s) + 9);
    }
    ZSTR_VAL(s)[45] = '\0';
    RETURN_STR(s);
}

PHP_METHOD(FastUuid_Uuid, getVersion) {
    ZEND_PARSE_PARAMETERS_NONE();
    fu_obj *u = fu_from_zobj(Z_OBJ_P(getThis()));
    if ((u->b[8] & 0xC0) != 0x80) RETURN_NULL();
    RETURN_LONG((u->b[6] >> 4) & 0x0f);
}

PHP_METHOD(FastUuid_Uuid, getVariant) {
    ZEND_PARSE_PARAMETERS_NONE();
    fu_obj *u = fu_from_zobj(Z_OBJ_P(getThis()));
    unsigned char o = u->b[8];
    if ((o & 0x80) == 0x00) RETURN_LONG(0); /* NCS */
    if ((o & 0xC0) == 0x80) RETURN_LONG(2); /* RFC 4122 */
    if ((o & 0xE0) == 0xC0) RETURN_LONG(6); /* Microsoft */
    RETURN_LONG(7); /* future */
}

PHP_METHOD(FastUuid_Uuid, getInteger) {
    ZEND_PARSE_PARAMETERS_NONE();
    fu_obj *u = fu_from_zobj(Z_OBJ_P(getThis()));
    RETURN_STR(fu_to_decimal(u->b)); /* numeric string (ramsey wraps in IntegerObject) */
}

/* Decode the embedded timestamp of a time-based UUID into unix seconds +
   microseconds. Returns 1 for v1/v2/v6/v7, 0 for versions with no timestamp. */
static int fu_decode_time(const unsigned char *b, int64_t *secs, uint32_t *micros, bool round_us) {
    if ((b[8] & 0xC0) != 0x80) return 0;
    int ver = (b[6] >> 4) & 0x0f;
    if (ver == 1 || ver == 2 || ver == 6) {
        uint64_t t60;
        if (ver == 6) {
            t60 = ((uint64_t)b[0]<<52)|((uint64_t)b[1]<<44)|((uint64_t)b[2]<<36)
                 |((uint64_t)b[3]<<28)|((uint64_t)b[4]<<20)|((uint64_t)b[5]<<12)
                 |((uint64_t)(b[6]&0x0f)<<8)|b[7];
        } else {
            /* v1 full 60-bit; v2 zeros time_low (holds local id, coarse ~429s resolution). */
            uint64_t th = ((uint64_t)(b[6]&0x0f)<<8)|b[7];
            uint64_t tm = ((uint64_t)b[4]<<8)|b[5];
            uint64_t tl = (ver == 2) ? 0
                : ((uint64_t)b[0]<<24)|((uint64_t)b[1]<<16)|((uint64_t)b[2]<<8)|b[3];
            t60 = (th<<48)|(tm<<32)|tl;
        }
        /* The epoch offset is an exact number of seconds, so the remainder is unchanged. */
        int64_t s = (int64_t)(t60 / 10000000ULL) - (int64_t)(FU_GREG_UNIX_EPOCH / 10000000ULL);
        uint64_t frac = t60 % 10000000ULL;
        /* getDateTime rounds 100 ns ticks to the nearest microsecond, halves up, as ramsey/uuid 4.10
           does; getTimestampMillis keeps floor milliseconds. */
        uint32_t us = (uint32_t)((frac + (round_us ? 5ULL : 0ULL)) / 10ULL);
        if (us == 1000000U) { s++; us = 0; }
        *secs = s; *micros = us;
        return 1;
    }
    if (ver == 7) {
        uint64_t ms = ((uint64_t)b[0]<<40)|((uint64_t)b[1]<<32)|((uint64_t)b[2]<<24)
                     |((uint64_t)b[3]<<16)|((uint64_t)b[4]<<8)|b[5];
        *secs   = (int64_t)(ms / 1000ULL);
        *micros = (uint32_t)((ms % 1000ULL) * 1000ULL);
        return 1;
    }
    return 0;
}

PHP_METHOD(FastUuid_Uuid, getDateTime) {
    ZEND_PARSE_PARAMETERS_NONE();
    fu_obj *u = fu_from_zobj(Z_OBJ_P(getThis()));
    int64_t secs; uint32_t micros;
    if (!fu_decode_time(u->b, &secs, &micros, true)) {
        zend_throw_exception(fu_ex_unsupported, "UUID has no embedded timestamp", 0);
        RETURN_THROWS();
    }
    if (fu_make_datetime(return_value, secs, micros) == FAILURE) RETURN_THROWS();
}

PHP_METHOD(FastUuid_Uuid, getTimestampMillis) {
    ZEND_PARSE_PARAMETERS_NONE();
    const unsigned char *b = fu_from_zobj(Z_OBJ_P(getThis()))->b;
    if ((b[8] & 0xC0) != 0x80) {
        zend_throw_exception(fu_ex_unsupported, "UUID has no embedded timestamp", 0);
        RETURN_THROWS();
    }
    int ver = (b[6] >> 4) & 0x0f;
    int64_t millis;
    if (ver == 7) {
        millis = (int64_t)(((uint64_t)b[0] << 40) | ((uint64_t)b[1] << 32) | ((uint64_t)b[2] << 24)
                         | ((uint64_t)b[3] << 16) | ((uint64_t)b[4] << 8)  | b[5]);
    } else if (ver == 1 || ver == 2 || ver == 6) {
        uint64_t t60;
        if (ver == 6) {
            t60 = ((uint64_t)b[0] << 52) | ((uint64_t)b[1] << 44) | ((uint64_t)b[2] << 36)
                | ((uint64_t)b[3] << 28) | ((uint64_t)b[4] << 20) | ((uint64_t)b[5] << 12)
                | ((uint64_t)(b[6] & 0x0f) << 8) | b[7];
        } else {
            uint64_t th = ((uint64_t)(b[6] & 0x0f) << 8) | b[7];
            uint64_t tm = ((uint64_t)b[4] << 8) | b[5];
            uint64_t tl = (ver == 2) ? 0
                : ((uint64_t)b[0] << 24) | ((uint64_t)b[1] << 16) | ((uint64_t)b[2] << 8) | b[3];
            t60 = (th << 48) | (tm << 32) | tl;
        }
        millis = (int64_t)(t60 / 10000ULL) - (int64_t)(FU_GREG_UNIX_EPOCH / 10000ULL);
    } else {
        zend_throw_exception(fu_ex_unsupported, "UUID has no embedded timestamp", 0);
        RETURN_THROWS();
    }
    if (millis < (int64_t) ZEND_LONG_MIN || millis > (int64_t) ZEND_LONG_MAX) {
        zend_throw_exception(fu_ex_unsupported, "UUID timestamp cannot be represented as an integer on this PHP build", 0);
        RETURN_THROWS();
    }
    RETURN_LONG((zend_long) millis);
}

PHP_METHOD(FastUuid_Uuid, getFields) {
    /* ramsey returns a FieldsInterface object; the compat layer supplies
       that shape, so the core returns a hex array. */
    ZEND_PARSE_PARAMETERS_NONE();
    fu_obj *u = fu_from_zobj(Z_OBJ_P(getThis()));
    array_init_size(return_value, 6);
    char hx[32]; fu_hex32(u->b, hx);
    add_assoc_stringl_ex(return_value, "time_low",                  sizeof("time_low") - 1,                  hx,      8);
    add_assoc_stringl_ex(return_value, "time_mid",                  sizeof("time_mid") - 1,                  hx + 8,  4);
    add_assoc_stringl_ex(return_value, "time_hi_and_version",       sizeof("time_hi_and_version") - 1,       hx + 12, 4);
    add_assoc_stringl_ex(return_value, "clock_seq_hi_and_reserved", sizeof("clock_seq_hi_and_reserved") - 1, hx + 16, 2);
    add_assoc_stringl_ex(return_value, "clock_seq_low",             sizeof("clock_seq_low") - 1,             hx + 18, 2);
    add_assoc_stringl_ex(return_value, "node",                      sizeof("node") - 1,                      hx + 20, 12);
}

static zend_never_inline void fu_equals_slow(zval *return_value, const unsigned char self_b[16], zval *o) {
    unsigned char b[16];
    if (!fu_resolve_uuid(o, b)) {
        if (UNEXPECTED(EG(exception))) return;
        RETVAL_FALSE;
        return;
    }
    RETVAL_BOOL(memcmp(self_b, b, 16) == 0);
}

static zend_never_inline void fu_compare_slow(zval *return_value, const unsigned char self_b[16], zval *o) {
    unsigned char b[16];
    if (!fu_resolve_uuid(o, b)) {
        if (UNEXPECTED(EG(exception))) return;
        zend_throw_exception(fu_ex_invalid_arg, "Not comparable", 0);
        return;
    }
    int r = memcmp(self_b, b, 16);
    RETVAL_LONG(r < 0 ? -1 : (r > 0 ? 1 : 0));
}

PHP_METHOD(FastUuid_Uuid, equals) {
    zval *o;
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_ZVAL(o) ZEND_PARSE_PARAMETERS_END();
    fu_obj *self = fu_from_zobj(Z_OBJ_P(getThis()));
    if (EXPECTED(Z_TYPE_P(o) == IS_OBJECT && Z_OBJCE_P(o) == fast_uuid_ce)) {
        RETURN_BOOL(memcmp(self->b, fu_from_zobj(Z_OBJ_P(o))->b, 16) == 0);
    }
    fu_equals_slow(return_value, self->b, o);
}

PHP_METHOD(FastUuid_Uuid, compareTo) {
    zval *o;
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_ZVAL(o) ZEND_PARSE_PARAMETERS_END();
    fu_obj *self = fu_from_zobj(Z_OBJ_P(getThis()));
    if (EXPECTED(Z_TYPE_P(o) == IS_OBJECT && Z_OBJCE_P(o) == fast_uuid_ce)) {
        int r = memcmp(self->b, fu_from_zobj(Z_OBJ_P(o))->b, 16);
        RETURN_LONG(r < 0 ? -1 : (r > 0 ? 1 : 0));
    }
    fu_compare_slow(return_value, self->b, o);
}

PHP_METHOD(FastUuid_Uuid, jsonSerialize) {
    ZEND_PARSE_PARAMETERS_NONE();
    RETURN_STR(fu_str(fu_from_zobj(Z_OBJ_P(getThis()))));
}

/* The default serializer sees no properties and would restore the nil UUID. */
PHP_METHOD(FastUuid_Uuid, __serialize) {
    ZEND_PARSE_PARAMETERS_NONE();
    fu_obj *u = fu_from_zobj(Z_OBJ_P(getThis()));
    array_init_size(return_value, 1);
    add_index_stringl(return_value, 0, (char *)u->b, 16);
}

PHP_METHOD(FastUuid_Uuid, __unserialize) {
    HashTable *data;
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_ARRAY_HT(data) ZEND_PARSE_PARAMETERS_END();
    zval *zb = zend_hash_index_find(data, 0);
    if (!zb || Z_TYPE_P(zb) != IS_STRING || Z_STRLEN_P(zb) != 16) {
        zend_throw_exception(fu_ex_invalid_arg, "Invalid serialized UUID payload", 0);
        RETURN_THROWS();
    }
    fu_obj *u = fu_from_zobj(Z_OBJ_P(getThis()));
    if (u->str) { zend_string_release(u->str); u->str = NULL; }
    memcpy(u->b, Z_STRVAL_P(zb), 16);
}

PHP_METHOD(FastUuid_Uuid, __set_state) {
    HashTable *data;
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_ARRAY_HT(data) ZEND_PARSE_PARAMETERS_END();
    zval *zs = zend_hash_str_find(data, "uuid", sizeof("uuid") - 1);
    unsigned char b[16];
    if (!zs || Z_TYPE_P(zs) != IS_STRING || !fu_parse(Z_STRVAL_P(zs), Z_STRLEN_P(zs), b)) {
        zend_throw_exception(fu_ex_invalid_arg, "Invalid __set_state payload; expected ['uuid' => <uuid string>]", 0);
        RETURN_THROWS();
    }
    fu_return_uuid(return_value, b);
}

/* ------------------------------------------------------------------ */
/* procedural fast-path (zend_string return, no object alloc)         */
/* ------------------------------------------------------------------ */

/* Each procedural function has one zend_always_inline body (fu_fn_*) shared
   by its ZPP entry point (zif_*) and, on PHP 8.4+, its frameless handler
   (zflf_*), which the compiler binds to direct calls. Z_FLF_PARAM_* applies the
   calling frame's strict_types, so both entry points accept, coerce, and reject
   the same arguments. No parameter here is optional, so the compiler binds a
   frameless handler only when the argument count equals its arity; any other
   count takes the zif_* path and its ArgumentCountError. */

static zend_always_inline void fu_retval_uuid(zval *return_value, const unsigned char b[16], bool as_bytes) {
    if (as_bytes) {
        RETVAL_STRINGL((const char *)b, 16);
    } else {
        zend_string *s = zend_string_alloc(36, 0);
        fu_format36(b, ZSTR_VAL(s));
        ZSTR_VAL(s)[36] = '\0';
        RETVAL_STR(s);
    }
}

static zend_result fu_batch_count_arg(zend_long count, uint32_t *out) {
    if (count <= 0) {
        zend_throw_exception(fu_ex_invalid_arg, "count must be > 0", 0);
        return FAILURE;
    }
    if ((zend_ulong)count > HT_MAX_SIZE || (zend_ulong)count > FU_MAX_BATCH_COUNT) {
        zend_throw_exception_ex(fu_ex_invalid_arg, 0, "count is too large (max %u)", FU_MAX_BATCH_COUNT);
        return FAILURE;
    }
    *out = (uint32_t)count;
    return SUCCESS;
}

/* A frameless handler receives compiled variables by pointer, not by copy as
   ZPP does. Coercing either argument can run userland (__toString, an error
   handler on the null-argument deprecation) that reassigns the other argument's
   variable and frees a string already borrowed from it. Unless both arguments
   are already strings, pin both values in owned temporaries before coercing
   either, so the call sees exactly the values it was given. */
#if PHP_VERSION_ID >= 80400
static zend_always_inline bool fu_flf_str_owned(zval *arg, zend_string **dest, uint32_t arg_num) {
    if (EXPECTED(Z_TYPE_P(arg) == IS_STRING)) {
        *dest = Z_STR_P(arg);
        return true;
    }
    if (!zend_parse_arg_str_ex(arg, dest, /* null_check */ false, arg_num, /* frameless */ true)) {
        zend_wrong_parameter_type_error(arg_num, Z_EXPECTED_STRING, arg);
        return false;
    }
    return true;
}

static zend_never_inline bool fu_flf_long_slow(zval *arg, zend_long *dest, uint32_t arg_num) {
    if (!zend_parse_arg_long_ex(arg, dest, /* is_null */ NULL, /* null_check */ false, arg_num, /* frameless */ true)) {
        zend_wrong_parameter_type_error(arg_num, Z_EXPECTED_LONG, arg);
        return false;
    }
    return true;
}
#endif

/* Emit zif_fname and, on 8.4+, its frameless twin; both run `call`, which
   reads the parsed arguments as lval, str, or str1/str2. Slow coercion paths
   are outlined so exact-type frameless calls keep minimal stack frames. */
#define FU_FN_ARG0(fname, call) \
PHP_FUNCTION(fname) { \
    ZEND_PARSE_PARAMETERS_NONE(); \
    call; \
} \
FU_FLF_ONLY(ZEND_FRAMELESS_FUNCTION(fname, 0) { \
    call; \
})

#define FU_FN_LONG1(fname, call) \
PHP_FUNCTION(fname) { \
    zend_long lval; \
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_LONG(lval) ZEND_PARSE_PARAMETERS_END(); \
    call; \
} \
FU_FLF_ONLY( \
static zend_never_inline void fu_flf_slow_##fname(zval *return_value, zval *arg1) { \
    zend_long lval; \
    if (fu_flf_long_slow(arg1, &lval, 1)) { \
        call; \
    } \
} \
ZEND_FRAMELESS_FUNCTION(fname, 1) { \
    if (EXPECTED(Z_TYPE_P(arg1) == IS_LONG)) { \
        zend_long lval = Z_LVAL_P(arg1); \
        call; \
        return; \
    } \
    fu_flf_slow_##fname(return_value, arg1); \
})

#define FU_FN_STR1(fname, call) \
PHP_FUNCTION(fname) { \
    zend_string *str; \
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_STR(str) ZEND_PARSE_PARAMETERS_END(); \
    call; \
} \
FU_FLF_ONLY( \
static zend_never_inline void fu_flf_slow_##fname(zval *return_value, zval *arg1) { \
    zval str_tmp; \
    zend_string *str; \
    ZVAL_COPY(&str_tmp, arg1); \
    if (fu_flf_str_owned(&str_tmp, &str, 1)) { \
        call; \
    } \
    zval_ptr_dtor(&str_tmp); \
} \
ZEND_FRAMELESS_FUNCTION(fname, 1) { \
    if (EXPECTED(Z_TYPE_P(arg1) == IS_STRING)) { \
        zend_string *str = Z_STR_P(arg1); \
        call; \
        return; \
    } \
    fu_flf_slow_##fname(return_value, arg1); \
})

#define FU_FN_STR2(fname, call) \
PHP_FUNCTION(fname) { \
    zend_string *str1, *str2; \
    ZEND_PARSE_PARAMETERS_START(2, 2) Z_PARAM_STR(str1) Z_PARAM_STR(str2) ZEND_PARSE_PARAMETERS_END(); \
    call; \
} \
FU_FLF_ONLY( \
static zend_never_inline void fu_flf_slow_##fname(zval *return_value, zval *arg1, zval *arg2) { \
    zval str1_tmp, str2_tmp; \
    zend_string *str1, *str2; \
    ZVAL_COPY(&str1_tmp, arg1); \
    ZVAL_COPY(&str2_tmp, arg2); \
    if (fu_flf_str_owned(&str1_tmp, &str1, 1) && fu_flf_str_owned(&str2_tmp, &str2, 2)) { \
        call; \
    } \
    zval_ptr_dtor(&str1_tmp); \
    zval_ptr_dtor(&str2_tmp); \
} \
ZEND_FRAMELESS_FUNCTION(fname, 2) { \
    if (EXPECTED(Z_TYPE_P(arg1) == IS_STRING && Z_TYPE_P(arg2) == IS_STRING)) { \
        zend_string *str1 = Z_STR_P(arg1); \
        zend_string *str2 = Z_STR_P(arg2); \
        call; \
        return; \
    } \
    fu_flf_slow_##fname(return_value, arg1, arg2); \
})

/* gen always inlines to a direct call: every caller passes a constant. */
static zend_always_inline void fu_fn_generate(zval *return_value, zend_result (*gen)(unsigned char *), bool as_bytes) {
    if (as_bytes) {
        zend_string *out = zend_string_alloc(16, 0);
        if (UNEXPECTED(gen((unsigned char *)ZSTR_VAL(out)) == FAILURE)) {
            zend_string_efree(out);
            return;
        }
        ZSTR_VAL(out)[16] = '\0';
        RETVAL_STR(out);
        return;
    }
    unsigned char b[16];
    if (UNEXPECTED(gen(b) == FAILURE)) return;
    fu_retval_uuid(return_value, b, false);
}

static zend_never_inline void fu_fn_v4_str_slow(zval *return_value) {
    unsigned char b[16];
    if (fu_rand_slow(b, 16) == FAILURE) return;
    fu_set_ver_var(b, 0x40);
    fu_retval_uuid(return_value, b, false);
}

static zend_always_inline void fu_fn_v4_str(zval *return_value) {
    size_t rpos = FAST_UUID_G(rpos);
    if (UNEXPECTED(16 > sizeof(FAST_UUID_G(rbuf)) - rpos)) {
        fu_fn_v4_str_slow(return_value);
        return;
    }
    const unsigned char *raw = FAST_UUID_G(rbuf) + rpos;
    FAST_UUID_G(rpos) = rpos + 16;
    zend_string *s = zend_string_alloc(36, 0);
    fu_format36_v4(raw, ZSTR_VAL(s));
    ZSTR_VAL(s)[36] = '\0';
    RETVAL_STR(s);
}

FU_FN_ARG0(uuid_v1,          fu_fn_generate(return_value, fu_gen_v1, 0))
FU_FN_ARG0(uuid_v1_bin,      fu_fn_generate(return_value, fu_gen_v1, 1))
FU_FN_ARG0(uuid_v4,          fu_fn_v4_str(return_value))
FU_FN_ARG0(uuid_v4_bin,      fu_fn_generate(return_value, fu_gen_v4, 1))
FU_FN_ARG0(uuid_v4_fast,     fu_fn_generate(return_value, fu_gen_v4_fast, 0))
FU_FN_ARG0(uuid_v4_fast_bin, fu_fn_generate(return_value, fu_gen_v4_fast, 1))
FU_FN_ARG0(uuid_v6,          fu_fn_generate(return_value, fu_gen_v6, 0))
FU_FN_ARG0(uuid_v6_bin,      fu_fn_generate(return_value, fu_gen_v6, 1))
FU_FN_ARG0(uuid_v7,          fu_fn_generate(return_value, fu_gen_v7, 0))
FU_FN_ARG0(uuid_v7_bin,      fu_fn_generate(return_value, fu_gen_v7, 1))

static zend_always_inline void fu_fn_v7_at(zval *return_value, zend_long ms, bool as_bytes) {
    if (as_bytes) {
        if (UNEXPECTED(ms < 0 || (uint64_t)ms > FU_MS48_MAX)) {
            zend_throw_exception(fu_ex_invalid_arg, "v7 millisecond timestamp out of range (0 .. 281474976710655)", 0);
            return;
        }
        zend_string *out = zend_string_alloc(16, 0);
        if (UNEXPECTED(fu_gen_v7_at((unsigned char *)ZSTR_VAL(out), (uint64_t)ms, 0) == FAILURE)) {
            zend_string_efree(out);
            return;
        }
        ZSTR_VAL(out)[16] = '\0';
        RETVAL_STR(out);
        return;
    }
    unsigned char b[16];
    if (fu_v7_at_ms(b, ms) == FAILURE) return;
    fu_retval_uuid(return_value, b, false);
}

FU_FN_LONG1(uuid_v7_at,     fu_fn_v7_at(return_value, lval, 0))
FU_FN_LONG1(uuid_v7_at_bin, fu_fn_v7_at(return_value, lval, 1))

static zend_always_inline void fu_fn_name_based(zval *return_value, zend_string *zns, zend_string *nm,
        void (*gen)(unsigned char *, const unsigned char *, const char *, size_t), bool as_bytes) {
    unsigned char ns[16], b[16];
    if (UNEXPECTED(!fu_parse(ZSTR_VAL(zns), ZSTR_LEN(zns), ns))) {
        zend_throw_exception(fu_ex_invalid_arg, "Invalid namespace", 0);
        return;
    }
    gen(b, ns, ZSTR_VAL(nm), ZSTR_LEN(nm));
    if (UNEXPECTED(EG(exception))) return; /* name cap: b is unwritten */
    fu_retval_uuid(return_value, b, as_bytes);
}

FU_FN_STR2(uuid_v3,     fu_fn_name_based(return_value, str1, str2, fu_gen_v3, 0))
FU_FN_STR2(uuid_v3_bin, fu_fn_name_based(return_value, str1, str2, fu_gen_v3, 1))
FU_FN_STR2(uuid_v5,     fu_fn_name_based(return_value, str1, str2, fu_gen_v5, 0))
FU_FN_STR2(uuid_v5_bin, fu_fn_name_based(return_value, str1, str2, fu_gen_v5, 1))

static zend_always_inline void fu_fn_v8(zval *return_value, zend_string *data, bool as_bytes) {
    if (UNEXPECTED(ZSTR_LEN(data) != 16)) {
        zend_throw_exception(fu_ex_invalid_arg, "uuid8 requires 16 bytes", 0);
        return;
    }
    if (as_bytes) {
        zend_string *out = zend_string_alloc(16, 0);
        fu_gen_v8((unsigned char *)ZSTR_VAL(out), (const unsigned char *)ZSTR_VAL(data));
        ZSTR_VAL(out)[16] = '\0';
        RETVAL_STR(out);
        return;
    }
    unsigned char b[16];
    fu_gen_v8(b, (const unsigned char *)ZSTR_VAL(data));
    fu_retval_uuid(return_value, b, false);
}

FU_FN_STR1(uuid_v8,     fu_fn_v8(return_value, str, 0))
FU_FN_STR1(uuid_v8_bin, fu_fn_v8(return_value, str, 1))

/* v4 batch: consume CSPRNG bytes directly from rbuf in chunked slices,
   updating rpos ahead of string allocations and formatting straight into
   each zend_string without an intermediate stack copy. */
static zend_result fu_gen_v4_batch_n(zval *arr, uint32_t n, int as_bytes) {
    uint32_t done = 0;
    zend_result rc = SUCCESS;
    zend_hash_real_init_packed(Z_ARRVAL_P(arr));
    ZEND_ASSERT(Z_ARRVAL_P(arr)->nTableSize >= n);
    /* arr was sized for n slots, so the fill never needs to grow. A CSPRNG
       failure breaks out and still runs FILL_END, so the array the caller
       discards holds exactly the elements added so far. */
    ZEND_HASH_FILL_PACKED(Z_ARRVAL_P(arr)) {
        while (done < n) {
            size_t rpos = FAST_UUID_G(rpos);
            uint32_t avail = (uint32_t)((sizeof(FAST_UUID_G(rbuf)) - rpos) / 16);
            if (UNEXPECTED(avail == 0)) {
                if (fu_rand_fill(FAST_UUID_G(rbuf), sizeof(FAST_UUID_G(rbuf))) == FAILURE) {
                    rc = FAILURE;
                    break;
                }
                rpos = 0;
                avail = (uint32_t)(sizeof(FAST_UUID_G(rbuf)) / 16);
            }
            uint32_t take = (n - done < avail) ? (n - done) : avail;
            const unsigned char *raw = FAST_UUID_G(rbuf) + rpos;
            FAST_UUID_G(rpos) = rpos + (size_t)take * 16;
            if (as_bytes) {
                for (uint32_t i = 0; i < take; i++) {
                    zend_string *s = zend_string_alloc(16, 0);
                    memcpy(ZSTR_VAL(s), raw + (size_t)i * 16, 16);
                    fu_set_ver_var((unsigned char *)ZSTR_VAL(s), 0x40);
                    ZSTR_VAL(s)[16] = '\0';
                    ZEND_HASH_FILL_SET_STR(s);
                    ZEND_HASH_FILL_NEXT();
                }
            } else {
                for (uint32_t i = 0; i < take; i++) {
                    zend_string *s = zend_string_alloc(36, 0);
                    fu_format36_v4(raw + (size_t)i * 16, ZSTR_VAL(s));
                    ZSTR_VAL(s)[36] = '\0';
                    ZEND_HASH_FILL_SET_STR(s);
                    ZEND_HASH_FILL_NEXT();
                }
            }
            done += take;
        }
    } ZEND_HASH_FILL_END();
    return rc;
}

/* On failure return_value holds a partial array; the engine destroys the
   result of a throwing call. */
static zend_always_inline void fu_fn_batch(zval *return_value, zend_long count,
        zend_result (*gen)(zval *, uint32_t, int), int as_bytes) {
    uint32_t n;
    if (fu_batch_count_arg(count, &n) == FAILURE) return;
    array_init_size(return_value, n);
    gen(return_value, n, as_bytes);
}

FU_FN_LONG1(uuid_v4_batch,     fu_fn_batch(return_value, lval, fu_gen_v4_batch_n, 0))
FU_FN_LONG1(uuid_v4_bin_batch, fu_fn_batch(return_value, lval, fu_gen_v4_batch_n, 1))

static void fu_format_v7_prefix19(uint64_t key, char prefix[19]) {
    unsigned char hi8[8];
    uint64_t hi = ((key & 0x0ffffffffffff000ULL) << 4) | 0x7000ULL | (key & 0x0fffULL);
    fu_store_be64(hi8, hi);
    const char *L = fu_lut;
    for (int j = 0; j < 4; j++) { prefix[j*2] = L[hi8[j]*2]; prefix[j*2+1] = L[hi8[j]*2+1]; }
    prefix[8]  = '-';
    prefix[9]  = L[hi8[4]*2]; prefix[10] = L[hi8[4]*2+1];
    prefix[11] = L[hi8[5]*2]; prefix[12] = L[hi8[5]*2+1];
    prefix[13] = '-';
    prefix[14] = L[hi8[6]*2]; prefix[15] = L[hi8[6]*2+1];
    prefix[16] = L[hi8[7]*2]; prefix[17] = L[hi8[7]*2+1];
    prefix[18] = '-';
}

/* One clock read per batch, then advance the monotonic (key, rand_b)
   counter: the same output as n fu_gen_v7 calls within one tick. */
static zend_result fu_gen_v7_batch_n(zval *arr, uint32_t n, int as_bytes) {
    uint64_t key, randb;
    if (fu_v7_now_key(&key) == FAILURE) return FAILURE;
    if (fu_v7_start(&key, &randb) == FAILURE) return FAILURE;

    zend_result rc = SUCCESS;
    zend_hash_real_init_packed(Z_ARRVAL_P(arr));
    ZEND_ASSERT(Z_ARRVAL_P(arr)->nTableSize >= n);
    ZEND_HASH_FILL_PACKED(Z_ARRVAL_P(arr)) {
        if (as_bytes) {
            uint64_t hi_be = fu_bswap64(((key & 0x0ffffffffffff000ULL) << 4) | 0x7000ULL | (key & 0x0fffULL));
            {
                zend_string *s = zend_string_alloc(16, 0);
                memcpy(ZSTR_VAL(s), &hi_be, 8);
                fu_store_be64((unsigned char *)ZSTR_VAL(s) + 8, 0x8000000000000000ULL | randb);
                ZSTR_VAL(s)[16] = '\0';
                ZEND_HASH_FILL_SET_STR(s);
                ZEND_HASH_FILL_NEXT();
            }
            uint32_t rem = n - 1;
            while (rem > 0) {
                size_t rpos = FAST_UUID_G(rpos);
                uint32_t avail = (uint32_t)((sizeof(FAST_UUID_G(rbuf)) - rpos) / 3);
                if (UNEXPECTED(avail == 0)) {
                    if (fu_rand_fill(FAST_UUID_G(rbuf), sizeof(FAST_UUID_G(rbuf))) == FAILURE) {
                        rc = FAILURE;
                        break;
                    }
                    rpos = 0;
                    avail = (uint32_t)(sizeof(FAST_UUID_G(rbuf)) / 3);
                }
                uint32_t take = (rem < avail) ? rem : avail;
                const unsigned char *rp = FAST_UUID_G(rbuf) + rpos;
                for (uint32_t j = 0; j < take; j++) {
                    uint64_t step = ((uint64_t)rp[0] << 16 | (uint64_t)rp[1] << 8 | rp[2]) + 1;
                    rp += 3;
                    uint64_t next = randb + step;
                    if (UNEXPECTED(next > FU_RANDB_MASK)) {
                        if (UNEXPECTED(key == ((1ULL << 60) - 1))) {
                            FAST_UUID_G(rpos) = (size_t)(rp - FAST_UUID_G(rbuf));
                            fu_v7_exhausted();
                            rc = FAILURE;
                            goto v7_batch_done;
                        }
                        key++;
                        next &= FU_RANDB_MASK;
                        hi_be = fu_bswap64(((key & 0x0ffffffffffff000ULL) << 4) | 0x7000ULL | (key & 0x0fffULL));
                    }
                    randb = next;
                    zend_string *s = zend_string_alloc(16, 0);
                    memcpy(ZSTR_VAL(s), &hi_be, 8);
                    fu_store_be64((unsigned char *)ZSTR_VAL(s) + 8, 0x8000000000000000ULL | randb);
                    ZSTR_VAL(s)[16] = '\0';
                    ZEND_HASH_FILL_SET_STR(s);
                    ZEND_HASH_FILL_NEXT();
                }
                FAST_UUID_G(rpos) = (size_t)(rp - FAST_UUID_G(rbuf));
                rem -= take;
            }
        } else {
            char prefix[19];
            unsigned char tail8[8];
            fu_format_v7_prefix19(key, prefix);
            {
                fu_store_be64(tail8, 0x8000000000000000ULL | randb);
                zend_string *s = zend_string_alloc(36, 0);
                memcpy(ZSTR_VAL(s), prefix, 19);
                fu_format_v7_tail17(tail8, ZSTR_VAL(s));
                ZSTR_VAL(s)[36] = '\0';
                ZEND_HASH_FILL_SET_STR(s);
                ZEND_HASH_FILL_NEXT();
            }
            uint32_t rem = n - 1;
            while (rem > 0) {
                size_t rpos = FAST_UUID_G(rpos);
                uint32_t avail = (uint32_t)((sizeof(FAST_UUID_G(rbuf)) - rpos) / 3);
                if (UNEXPECTED(avail == 0)) {
                    if (fu_rand_fill(FAST_UUID_G(rbuf), sizeof(FAST_UUID_G(rbuf))) == FAILURE) {
                        rc = FAILURE;
                        break;
                    }
                    rpos = 0;
                    avail = (uint32_t)(sizeof(FAST_UUID_G(rbuf)) / 3);
                }
                uint32_t take = (rem < avail) ? rem : avail;
                const unsigned char *rp = FAST_UUID_G(rbuf) + rpos;
                for (uint32_t j = 0; j < take; j++) {
                    uint64_t step = ((uint64_t)rp[0] << 16 | (uint64_t)rp[1] << 8 | rp[2]) + 1;
                    rp += 3;
                    uint64_t next = randb + step;
                    if (UNEXPECTED(next > FU_RANDB_MASK)) {
                        if (UNEXPECTED(key == ((1ULL << 60) - 1))) {
                            FAST_UUID_G(rpos) = (size_t)(rp - FAST_UUID_G(rbuf));
                            fu_v7_exhausted();
                            rc = FAILURE;
                            goto v7_batch_done;
                        }
                        key++;
                        next &= FU_RANDB_MASK;
                        fu_format_v7_prefix19(key, prefix);
                    }
                    randb = next;
                    fu_store_be64(tail8, 0x8000000000000000ULL | randb);
                    zend_string *s = zend_string_alloc(36, 0);
                    memcpy(ZSTR_VAL(s), prefix, 19);
                    fu_format_v7_tail17(tail8, ZSTR_VAL(s));
                    ZSTR_VAL(s)[36] = '\0';
                    ZEND_HASH_FILL_SET_STR(s);
                    ZEND_HASH_FILL_NEXT();
                }
                FAST_UUID_G(rpos) = (size_t)(rp - FAST_UUID_G(rbuf));
                rem -= take;
            }
        }
v7_batch_done: ;
    } ZEND_HASH_FILL_END();
    FAST_UUID_G(v7_key) = key;
    FAST_UUID_G(v7_randb) = randb;
    FAST_UUID_G(v7_initialized) = 1;
    return rc;
}

FU_FN_LONG1(uuid_v7_batch,     fu_fn_batch(return_value, lval, fu_gen_v7_batch_n, 0))
FU_FN_LONG1(uuid_v7_bin_batch, fu_fn_batch(return_value, lval, fu_gen_v7_batch_n, 1))

static zend_always_inline void fu_fn_to_bin(zval *return_value, zend_string *s) {
    zend_string *out = zend_string_alloc(16, 0);
    if (UNEXPECTED(!fu_parse(ZSTR_VAL(s), ZSTR_LEN(s), (unsigned char *)ZSTR_VAL(out)))) {
        zend_string_efree(out);
        zend_throw_exception(fu_ex_invalid_str, "Invalid UUID", 0);
        return;
    }
    ZSTR_VAL(out)[16] = '\0';
    RETVAL_STR(out);
}

static zend_always_inline void fu_fn_from_bin(zval *return_value, zend_string *s) {
    if (UNEXPECTED(ZSTR_LEN(s) != 16)) {
        zend_throw_exception(fu_ex_invalid_arg, "Expected 16 bytes", 0);
        return;
    }
    fu_retval_uuid(return_value, (const unsigned char *)ZSTR_VAL(s), 0);
}

static zend_always_inline void fu_fn_is_valid(zval *return_value, zend_string *s, bool strict) {
    if (strict) {
        RETVAL_BOOL(fu_valid_strict(ZSTR_VAL(s), ZSTR_LEN(s)));
        return;
    }
    RETVAL_BOOL(fu_valid_tolerant(ZSTR_VAL(s), ZSTR_LEN(s)));
}

FU_FN_STR1(uuid_to_bin,   fu_fn_to_bin(return_value, str))
FU_FN_STR1(uuid_from_bin, fu_fn_from_bin(return_value, str))

PHP_FUNCTION(uuid_is_valid) {
    zend_string *str;
    bool strict = false;
    ZEND_PARSE_PARAMETERS_START(1, 2) Z_PARAM_STR(str) Z_PARAM_OPTIONAL Z_PARAM_BOOL(strict) ZEND_PARSE_PARAMETERS_END();
    fu_fn_is_valid(return_value, str, strict);
}
FU_FLF_ONLY(
static zend_never_inline void fu_flf_slow_uuid_is_valid_1(zval *return_value, zval *arg1) {
    zval str_tmp;
    zend_string *str;
    ZVAL_COPY(&str_tmp, arg1);
    if (fu_flf_str_owned(&str_tmp, &str, 1)) {
        fu_fn_is_valid(return_value, str, false);
    }
    zval_ptr_dtor(&str_tmp);
}
ZEND_FRAMELESS_FUNCTION(uuid_is_valid, 1) {
    if (EXPECTED(Z_TYPE_P(arg1) == IS_STRING)) {
        RETVAL_BOOL(fu_valid_tolerant(Z_STRVAL_P(arg1), Z_STRLEN_P(arg1)));
        return;
    }
    fu_flf_slow_uuid_is_valid_1(return_value, arg1);
})
/* As in FU_FN_STR2: unless both arguments already have their types, pin both before coercing either. */
FU_FLF_ONLY(
static zend_never_inline void fu_flf_slow_uuid_is_valid_2(zval *return_value, zval *arg1, zval *arg2) {
    zval str_tmp, strict_tmp;
    ZVAL_COPY(&str_tmp, arg1);
    ZVAL_COPY(&strict_tmp, arg2);
    zend_string *str;
    bool strict;
    if (fu_flf_str_owned(&str_tmp, &str, 1)) {
        if (zend_parse_arg_bool_ex(&strict_tmp, &strict, NULL, /* null_check */ false, 2, /* frameless */ true)) {
            fu_fn_is_valid(return_value, str, strict);
        } else {
            zend_wrong_parameter_type_error(2, Z_EXPECTED_BOOL, &strict_tmp);
        }
    }
    zval_ptr_dtor(&str_tmp);
    zval_ptr_dtor(&strict_tmp);
}
ZEND_FRAMELESS_FUNCTION(uuid_is_valid, 2) {
    if (EXPECTED(Z_TYPE_P(arg1) == IS_STRING && (Z_TYPE_P(arg2) == IS_TRUE || Z_TYPE_P(arg2) == IS_FALSE))) {
        fu_fn_is_valid(return_value, Z_STR_P(arg1), Z_TYPE_P(arg2) == IS_TRUE);
        return;
    }
    fu_flf_slow_uuid_is_valid_2(return_value, arg1, arg2);
})

static zend_always_inline void fu_fn_random_bytes(zval *return_value, zend_long n) {
    if (UNEXPECTED(n <= 0)) { zend_throw_exception(fu_ex_invalid_arg, "length must be > 0", 0); return; }
    if (UNEXPECTED((zend_ulong)n > FU_MAX_RANDOM_BYTES)) { zend_throw_exception_ex(fu_ex_invalid_arg, 0, "length is too large (max %u)", FU_MAX_RANDOM_BYTES); return; }
    zend_string *s = zend_string_alloc((size_t)n, 0);
    zend_result result;
    if (UNEXPECTED((size_t)n > sizeof(FAST_UUID_G(rbuf)) / 2 && (size_t)n < sizeof(FAST_UUID_G(rbuf)))) {
        result = fu_rand_fill((unsigned char *)ZSTR_VAL(s), (size_t)n);
    } else {
        result = fu_rand((unsigned char *)ZSTR_VAL(s), (size_t)n);
    }
    if (UNEXPECTED(result == FAILURE)) { zend_string_efree(s); return; }
    ZSTR_VAL(s)[n] = '\0';
    RETVAL_STR(s);
}

FU_FN_LONG1(fast_uuid_random_bytes, fu_fn_random_bytes(return_value, lval))

#undef FU_FN_ARG0
#undef FU_FN_LONG1
#undef FU_FN_STR1
#undef FU_FN_STR2

#if PHP_VERSION_ID >= 80400
#include "Zend/zend_system_id.h"

#define FU_FLF_MAX 64 /* >= the stub's frameless handler count */

/* This module's frameless handlers in registration order: zend_register_functions()
   appends them to zend_flf_handlers as one contiguous run. */
static uint32_t fu_flf_handlers(void *out[], uint32_t cap) {
    uint32_t n = 0;
    for (const zend_function_entry *fe = ext_functions; fe->fname; fe++) {
        if (!fe->frameless_function_infos) {
            continue;
        }
        for (const zend_frameless_function_info *info = fe->frameless_function_infos; info->handler; info++) {
            if (n < cap) {
                out[n] = info->handler;
            }
            n++;
        }
    }
    ZEND_ASSERT(n <= cap);
    return n;
}

static bool fu_flf_run_at(size_t base, void *const handlers[], uint32_t n) {
    for (uint32_t i = 0; i < n; i++) {
        if (zend_flf_handlers[base + i] != handlers[i]) {
            return false;
        }
    }
    return true;
}

/* Index of this module's run of slots in zend_flf_handlers, or UINT32_MAX.
   Under dl() registration immediately precedes MINIT, so the run is the tail
   and is checked first; persistent loads register every extension before any
   MINIT, so later extensions' slots may follow ours. */
static uint32_t fu_flf_first_slot(void *const handlers[], uint32_t n) {
    size_t end = 0;
    while (zend_flf_handlers && zend_flf_handlers[end]) {
        end++;
    }
    if (n == 0 || end < n || end - n >= UINT32_MAX) {
        return UINT32_MAX;
    }
    if (fu_flf_run_at(end - n, handlers, n)) {
        return (uint32_t)(end - n);
    }
    for (size_t base = 0; base + n <= end; base++) {
        if (fu_flf_run_at(base, handlers, n)) {
            return (uint32_t)base;
        }
    }
    return UINT32_MAX;
}

/* opcache's file cache persists FRAMELESS_ICALL opcodes with raw
   zend_flf_handlers indices, and zend_system_id does not cover the loaded
   extension set. A cache written by a process that registered these handlers
   at other indices (or not at all) would otherwise dispatch through the wrong
   slot. Key the system id to this module's slot range and version. Only a
   persistent load can do this: the id is finalized after startup MINITs. */
static void fu_flf_key_system_id(void) {
    void *handlers[FU_FLF_MAX];
    uint32_t n = fu_flf_handlers(handlers, FU_FLF_MAX);
    uint32_t key[2] = { n <= FU_FLF_MAX ? fu_flf_first_slot(handlers, n) : UINT32_MAX, n };
    zend_add_system_entropy("fast_uuid", "zend_flf_handlers", key, sizeof(key));
    zend_add_system_entropy("fast_uuid", "version", PHP_FAST_UUID_VERSION, sizeof(PHP_FAST_UUID_VERSION) - 1);
}

/* A dl()-loaded (MODULE_TEMPORARY) copy must never be bound as a frameless
   call. zend_register_functions() appends every frameless handler to the
   process-global zend_flf_handlers/zend_flf_functions tables for temporary
   modules too, and nothing removes them at unload. The next request's dl()
   usually maps the .so at the same address, so the compiler would match a
   handler pointer to the stale entry and ZEND_FLF_FUNC() would read the freed
   zend_internal_function (argument errors, backtraces, observers).
   Runs from MINIT, which dl() calls right after registration with no
   compilation in between: clearing frameless_function_infos keeps the compiler
   on the regular call path, and overwriting this load's handler slots with an
   engine data address (never a function address, never unmapped) means no
   later-mapped handler can resolve to them. The slots are overwritten, not
   removed: the table is NULL-terminated and indices already in use must hold.
   Core still appends this load's slots on every dl(); they are never reclaimed. */
static void fu_flf_disable_for_dl(void) {
    for (const zend_function_entry *fe = ext_functions; fe->fname; fe++) {
        if (!fe->frameless_function_infos) {
            continue;
        }
        zend_function *fn = zend_hash_str_find_ptr_lc(CG(function_table), fe->fname, strlen(fe->fname));
        if (fn && fn->type == ZEND_INTERNAL_FUNCTION
                && fn->internal_function.frameless_function_infos == fe->frameless_function_infos) {
            fn->internal_function.frameless_function_infos = NULL;
        }
    }
    void *handlers[FU_FLF_MAX];
    uint32_t n = fu_flf_handlers(handlers, FU_FLF_MAX);
    uint32_t base = n <= FU_FLF_MAX ? fu_flf_first_slot(handlers, n) : UINT32_MAX;
    if (base != UINT32_MAX) {
        for (uint32_t i = 0; i < n; i++) {
            zend_flf_handlers[base + i] = (void *)&zend_flf_handlers;
        }
        return;
    }
    /* Run not found (a table this code does not expect): overwrite every match. */
    for (const zend_function_entry *fe = ext_functions; fe->fname; fe++) {
        if (!fe->frameless_function_infos) {
            continue;
        }
        for (const zend_frameless_function_info *info = fe->frameless_function_infos; info->handler; info++) {
            for (void **slot = zend_flf_handlers; slot && *slot; slot++) {
                if (*slot == info->handler) {
                    *slot = (void *)&zend_flf_handlers;
                }
            }
        }
    }
}
#endif

/* ------------------------------------------------------------------ */
/* module lifecycle                                                   */
/* ------------------------------------------------------------------ */

static PHP_GINIT_FUNCTION(fast_uuid) {
#if defined(COMPILE_DL_FAST_UUID) && defined(ZTS)
    ZEND_TSRMLS_CACHE_UPDATE();
#endif
    memset(fast_uuid_globals, 0, sizeof(*fast_uuid_globals));
    fast_uuid_globals->rpos = sizeof(fast_uuid_globals->rbuf); /* force first-use refill */
}

/* The buffer still holds bytes already handed out as UUIDs; under ZTS this
   memory goes back to the allocator when the thread exits. */
static PHP_GSHUTDOWN_FUNCTION(fast_uuid) {
    ZEND_SECURE_ZERO(fast_uuid_globals->rbuf, sizeof(fast_uuid_globals->rbuf));
    ZEND_SECURE_ZERO(fast_uuid_globals->drbg_key, sizeof(fast_uuid_globals->drbg_key));
    ZEND_SECURE_ZERO(fast_uuid_globals->drbg_ctr, sizeof(fast_uuid_globals->drbg_ctr));
    ZEND_SECURE_ZERO(fast_uuid_globals->prng_s, sizeof(fast_uuid_globals->prng_s));
}

PHP_MINIT_FUNCTION(fast_uuid) {
#if PHP_VERSION_ID >= 80400
    if (type == MODULE_TEMPORARY) {
        fu_flf_disable_for_dl();
    } else {
        fu_flf_key_system_id();
    }
#endif
    for (int i = 0; i < 256; i++) {
        fu_lut[i*2] = fu_hexd[i >> 4];
        fu_lut[i*2+1] = fu_hexd[i & 0x0f];
        fu_nib_lut[i] = -1;
    }
    for (int i = 0; i < 10; i++) {
        fu_nib_lut[(unsigned char)('0' + i)] = i;
    }
    for (int i = 0; i < 6; i++) {
        fu_nib_lut[(unsigned char)('a' + i)] = 10 + i;
        fu_nib_lut[(unsigned char)('A' + i)] = 10 + i;
    }

#ifndef PHP_WIN32
    /* Register once per process; a second MINIT (dl() after a static load)
       must not stack duplicate handlers. There is no unregister API: glibc
       drops a DSO's atfork handlers at dlclose, musl does not, so unloading a
       dl()-loaded copy on musl leaves a dangling handler. */
    static int atfork_registered = 0;
    if (!atfork_registered) {
        /* Latch only on success so a later MINIT can retry. Without the
           handler, forked children share generator state; warn at load. */
        if (pthread_atfork(NULL, NULL, fu_atfork_child) == 0) {
            atfork_registered = 1;
        } else {
            php_error_docref(NULL, E_WARNING,
                "fast_uuid: pthread_atfork registration failed; forked children may duplicate UUID output");
        }
#if defined(__linux__) && !defined(__GLIBC__)
        /* musl keeps atfork handlers across dlclose, so a dl()-loaded copy
           crashes the next fork() after unload. Warn at load. */
        if (fast_uuid_module_entry.type == MODULE_TEMPORARY) {
            php_error_docref(NULL, E_WARNING,
                "fast_uuid: dl() loading is unsupported on this libc (dangling atfork handler after unload); use extension= instead");
        }
#endif
    }
#else
    {
        HMODULE k32 = GetModuleHandleW(L"kernel32.dll");
        if (k32) {
            fu_precise_time = (fu_precise_time_fn)GetProcAddress(k32, "GetSystemTimePreciseAsFileTime");
        }
    }
#endif

#ifdef FU_X86
    fu_has_ssse3 = fu_detect_ssse3();
#endif

#ifdef FU_HAVE_AES
    /* A backend that fails its known-answer test is never used: the OS
       CSPRNG refill path stays in force instead of emitting bad output. */
    int has_aes = fu_detect_aes();
    if (has_aes < 0) {
        fu_aes_state = FU_AES_NO_DETECT;
    } else if (has_aes == 0) {
        fu_aes_state = FU_AES_NO_CPU;
    } else if (!fu_aes_selftest()) {
        fu_aes_state = FU_AES_SELFTEST_FAILED;
    } else {
        fu_aes_state = FU_AES_ACTIVE;
    }
#endif

#ifdef FU_HAVE_SHA1_HW
    fu_sha1_active = fu_detect_sha1() && fu_sha1_selftest();
#endif

    zend_class_entry *marker_ce = register_class_FastUuid_UuidInterface(php_json_serializable_ce, zend_ce_stringable);
    if (!marker_ce) {
        php_error_docref(NULL, E_WARNING, "fast_uuid: could not register FastUuid\\UuidInterface");
        return FAILURE;
    }
    zend_class_entry *iface_ce = register_class_FastUuid_UuidValueInterface(marker_ce);
    if (!iface_ce) {
        php_error_docref(NULL, E_WARNING, "fast_uuid: could not register FastUuid\\UuidValueInterface");
        return FAILURE;
    }
    fast_uuid_ce = register_class_FastUuid_Uuid(iface_ce);
    if (!fast_uuid_ce) {
        php_error_docref(NULL, E_WARNING, "fast_uuid: could not register FastUuid\\Uuid");
        return FAILURE;
    }
    fast_uuid_ce->create_object = fu_create;
    ZEND_ASSERT(fast_uuid_ce->default_properties_count == 0);

    memcpy(&fu_handlers, zend_get_std_object_handlers(), sizeof(zend_object_handlers));
    fu_handlers.offset             = offsetof(fu_obj, std);
    fu_handlers.free_obj           = fu_free;
    fu_handlers.clone_obj          = fu_clone;
    fu_handlers.compare            = fu_compare;
    fu_handlers.cast_object        = fu_cast;
    fu_handlers.get_debug_info     = fu_get_debug_info;
    fu_handlers.get_properties_for = fu_get_properties_for;
#if PHP_VERSION_ID >= 80300
    fast_uuid_ce->default_object_handlers = &fu_handlers;
#endif

    /* exception hierarchy (ramsey/uuid 4.x-shaped): InvalidUuidString <-
       InvalidArgument <- \InvalidArgumentException; UnsupportedOperation <-
       \LogicException (ramsey uses LogicException, not RuntimeException). */
    zend_class_entry *uuid_exception_ce =
        register_class_FastUuid_Exception_UuidExceptionInterface(zend_ce_throwable);
    if (!uuid_exception_ce) {
        php_error_docref(NULL, E_WARNING, "fast_uuid: could not register FastUuid\\Exception\\UuidExceptionInterface");
        return FAILURE;
    }
    fu_ex_invalid_arg = register_class_FastUuid_Exception_InvalidArgumentException(
        spl_ce_InvalidArgumentException, uuid_exception_ce);
    if (!fu_ex_invalid_arg) {
        php_error_docref(NULL, E_WARNING, "fast_uuid: could not register FastUuid\\Exception\\InvalidArgumentException");
        return FAILURE;
    }
    fu_ex_invalid_str = register_class_FastUuid_Exception_InvalidUuidStringException(
        fu_ex_invalid_arg, uuid_exception_ce);
    if (!fu_ex_invalid_str) {
        php_error_docref(NULL, E_WARNING, "fast_uuid: could not register FastUuid\\Exception\\InvalidUuidStringException");
        return FAILURE;
    }
    fu_ex_unsupported = register_class_FastUuid_Exception_UnsupportedOperationException(
        spl_ce_LogicException, uuid_exception_ce);
    if (!fu_ex_unsupported) {
        php_error_docref(NULL, E_WARNING, "fast_uuid: could not register FastUuid\\Exception\\UnsupportedOperationException");
        return FAILURE;
    }

    fu_str_getcore = zend_string_init("getcore", sizeof("getcore") - 1, 1);
    zend_string_hash_val(fu_str_getcore);

    return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(fast_uuid) {
    if (fu_str_getcore) {
        zend_string_release(fu_str_getcore);
        fu_str_getcore = NULL;
    }
    return SUCCESS;
}

PHP_MINFO_FUNCTION(fast_uuid) {
    php_info_print_table_start();
    php_info_print_table_row(2, "fast_uuid support", "enabled");
    php_info_print_table_row(2, "version", PHP_FAST_UUID_VERSION);
#ifndef PHP_WIN32
    php_info_print_table_row(2, "fork safety", "pthread_atfork child-state reset"
#if defined(__linux__) && !defined(__GLIBC__)
        " (on this libc load via extension=, not dl())"
#endif
    );
#endif
#ifdef FU_X86
    php_info_print_table_row(2, "SIMD hex formatter", fu_has_ssse3 ? "SSSE3" : "scalar (CPU lacks SSSE3)");
#elif defined(FU_AARCH64)
    php_info_print_table_row(2, "SIMD hex formatter", "AArch64 NEON");
#else
    php_info_print_table_row(2, "SIMD hex formatter", "scalar");
#endif
    {
        char rng[160];
        const char *why = "";
        switch (fu_aes_state) {
            case FU_AES_ACTIVE: break;
            case FU_AES_NO_CPU: why = " (CPU lacks AES instructions)"; break;
            case FU_AES_NO_DETECT: why = " (AES detection unsupported on this OS)"; break;
            case FU_AES_SELFTEST_FAILED: why = " (AES known-answer self-test failed)"; break;
            default: why = " (no hardware AES backend in this build)"; break;
        }
#ifdef FU_HAVE_AES
        if (fu_aes_state == FU_AES_ACTIVE) {
            snprintf(rng, sizeof(rng),
                "AES-256-CTR DRBG (" FU_AES_NAME "), fast key erasure every %u KiB, OS reseed every %u KiB",
                (unsigned)(FU_DRBG_CHUNK / 1024), (unsigned)(FU_DRBG_RESEED_BYTES / 1024));
        } else
#endif
        {
            snprintf(rng, sizeof(rng), "OS CSPRNG (php_random_bytes), %u KiB batched%s",
                (unsigned)(sizeof(FAST_UUID_G(rbuf)) / 1024), why);
        }
        php_info_print_table_row(2, "CSPRNG", rng);
    }
#ifdef FU_HAVE_SHA1_HW
    {
        const char *sha1 = FU_SHA1_NAME;
        if (!fu_sha1_active) {
# if defined(FU_SHA1_ARM) && !defined(__linux__) && !defined(__APPLE__)
            sha1 = "software (SHA-1 detection unsupported on this OS)";
# else
            sha1 = fu_detect_sha1() ? "software (SHA-1 known-answer self-test failed)"
                                    : "software (CPU lacks SHA-1 instructions)";
# endif
        }
        php_info_print_table_row(2, "SHA-1 (v5)", sha1);
    }
#else
    php_info_print_table_row(2, "SHA-1 (v5)", "software (no hardware SHA-1 backend in this build)");
#endif
    php_info_print_table_end();
}

zend_module_entry fast_uuid_module_entry = {
    STANDARD_MODULE_HEADER,
    "fast_uuid",
    ext_functions,
    PHP_MINIT(fast_uuid),
    PHP_MSHUTDOWN(fast_uuid),
    NULL,
    NULL,
    PHP_MINFO(fast_uuid),
    PHP_FAST_UUID_VERSION,
    PHP_MODULE_GLOBALS(fast_uuid),
    PHP_GINIT(fast_uuid),
    PHP_GSHUTDOWN(fast_uuid),
    NULL,
    STANDARD_MODULE_PROPERTIES_EX
};

#ifdef COMPILE_DL_FAST_UUID
# ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
# endif
ZEND_GET_MODULE(fast_uuid)
#endif
