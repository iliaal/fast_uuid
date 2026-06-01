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

#ifndef PHP_WIN32
# include <unistd.h>   /* getuid/getgid for uuid2 auto local-identifier */
#endif

#if defined(__x86_64__) || defined(__i386__)
# include <immintrin.h>
# define FU_X86 1
static int fu_has_ssse3 = 0;   /* set in MINIT via __builtin_cpu_supports */
#endif

ZEND_DECLARE_MODULE_GLOBALS(fast_uuid)

static zend_class_entry *fast_uuid_ce;
static zend_class_entry *fast_uuid_iface_ce;
static zend_object_handlers fu_handlers;

/* byte->2hex lookup, built in MINIT */
static char fu_lut[512];
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

static zend_object *fu_create(zend_class_entry *ce) {
    fu_obj *u = zend_object_alloc(sizeof(fu_obj), ce);
    zend_object_std_init(&u->std, ce);
    u->std.handlers = &fu_handlers;
    u->str = NULL;
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
    zend_object *no = fu_create(o->ce);
    fu_obj *dst = fu_from_zobj(no);
    memcpy(dst->b, src->b, 16);
    zend_objects_clone_members(no, o);
    return no;
}

static int fu_compare(zval *a, zval *b) {
    if (Z_TYPE_P(a) != IS_OBJECT || Z_TYPE_P(b) != IS_OBJECT ||
        !instanceof_function(Z_OBJCE_P(a), fast_uuid_ce) ||
        !instanceof_function(Z_OBJCE_P(b), fast_uuid_ce)) {
        return ZEND_UNCOMPARABLE;
    }
    int r = memcmp(fu_from_zobj(Z_OBJ_P(a))->b, fu_from_zobj(Z_OBJ_P(b))->b, 16);
    return r < 0 ? -1 : (r > 0 ? 1 : 0);
}

/* ------------------------------------------------------------------ */
/* formatting / parsing                                               */
/* ------------------------------------------------------------------ */

/* scalar 16-byte -> 32 lowercase hex chars (default / non-x86 / no-SSSE3) */
static inline void fu_hex32_scalar(const unsigned char *b, char *o) {
    const char *L = fu_lut;
    for (int i = 0; i < 16; i++) { o[i*2] = L[b[i]*2]; o[i*2+1] = L[b[i]*2+1]; }
}

#ifdef FU_X86
/* SSSE3 pshufb-LUT path: 16 bytes -> 32 hex in a handful of vector ops.
   x86 only and runtime-gated on fu_has_ssse3. AVX2 offers no win for a single
   16-byte value (the work fits one XMM register); a batch API would be the
   place for a 256-bit path. */
__attribute__((target("ssse3")))
static void fu_hex32_ssse3(const unsigned char *b, char *o) {
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
#endif

static inline void fu_hex32(const unsigned char *b, char *o) {
#ifdef FU_X86
    if (fu_has_ssse3) { fu_hex32_ssse3(b, o); return; }
#endif
    fu_hex32_scalar(b, o);
}

/* canonical 8-4-4-4-12 into 36 bytes (no NUL) */
static inline void fu_format36(const unsigned char *b, char *o) {
    char h[32];
    fu_hex32(b, h);
    memcpy(o,      h,      8); o[8]  = '-';
    memcpy(o + 9,  h + 8,  4); o[13] = '-';
    memcpy(o + 14, h + 12, 4); o[18] = '-';
    memcpy(o + 19, h + 16, 4); o[23] = '-';
    memcpy(o + 24, h + 20, 12);
}

static inline void fu_format32(const unsigned char *b, char *o) {
    fu_hex32(b, o);
}

static inline int fu_nib(unsigned char c) {
    if (c >= '0' && c <= '9') return c - '0';
    c |= 0x20;
    if (c >= 'a' && c <= 'f') return c - 'a' + 10;
    return -1;
}

/* tolerant parser: accepts canonical 36, bare 32-hex, optional urn:uuid:/{} */
static int fu_parse(const char *s, size_t len, unsigned char out[16]) {
    if (len >= 9 && (s[0]=='u'||s[0]=='U') && zend_binary_strncasecmp(s, len, "urn:uuid:", 9, 9) == 0) {
        s += 9; len -= 9;
    }
    if (len >= 2 && s[0] == '{' && s[len-1] == '}') { s++; len -= 2; }

    if (len == 36) {
        if (s[8] != '-' || s[13] != '-' || s[18] != '-' || s[23] != '-') return 0;
        int j = 0;
        for (size_t i = 0; i < 36; i++) {
            if (i==8||i==13||i==18||i==23) continue;
            int hi = fu_nib((unsigned char)s[i]);   i++;
            int lo = fu_nib((unsigned char)s[i]);
            if (hi < 0 || lo < 0) return 0;
            out[j++] = (unsigned char)((hi << 4) | lo);
        }
        return 1;
    }
    if (len == 32) {
        for (int i = 0; i < 16; i++) {
            int hi = fu_nib((unsigned char)s[i*2]);
            int lo = fu_nib((unsigned char)s[i*2+1]);
            if (hi < 0 || lo < 0) return 0;
            out[i] = (unsigned char)((hi << 4) | lo);
        }
        return 1;
    }
    return 0;
}

static zend_string *fu_str(fu_obj *u) {
    if (u->str) return zend_string_copy(u->str);
    zend_string *s = zend_string_alloc(36, 0);
    fu_format36(u->b, ZSTR_VAL(s));
    ZSTR_VAL(s)[36] = '\0';
    u->str = s;
    return zend_string_copy(s);
}

static int fu_cast(zend_object *o, zval *ret, int type) {
    if (type == IS_STRING) { ZVAL_STR(ret, fu_str(fu_from_zobj(o))); return SUCCESS; }
    return zend_std_cast_object_tostring(o, ret, type);
}

/* ------------------------------------------------------------------ */
/* randomness                                                         */
/* ------------------------------------------------------------------ */

/* crypto-secure, batched */
static void fu_rand(unsigned char *dst, size_t n) {
    if (UNEXPECTED(n > sizeof(FAST_UUID_G(rbuf)))) {
        php_random_bytes_throw(dst, n);
        return;
    }
    if (FAST_UUID_G(rpos) + n > sizeof(FAST_UUID_G(rbuf))) {
        php_random_bytes_throw(FAST_UUID_G(rbuf), sizeof(FAST_UUID_G(rbuf)));
        FAST_UUID_G(rpos) = 0;
    }
    memcpy(dst, FAST_UUID_G(rbuf) + FAST_UUID_G(rpos), n);
    FAST_UUID_G(rpos) += n;
}

/* non-crypto xoshiro256** for uuid_v4_fast() */
static inline uint64_t fu_rotl(uint64_t x, int k) { return (x << k) | (x >> (64 - k)); }
static uint64_t fu_xs_next(void) {
    uint64_t *s = FAST_UUID_G(prng_s);
    if (UNEXPECTED(!FAST_UUID_G(prng_seeded))) {
        fu_rand((unsigned char *)s, 32);
        if ((s[0]|s[1]|s[2]|s[3]) == 0) s[0] = 0x9e3779b97f4a7c15ULL;
        FAST_UUID_G(prng_seeded) = 1;
    }
    uint64_t r = fu_rotl(s[1] * 5, 7) * 9;
    uint64_t t = s[1] << 17;
    s[2] ^= s[0]; s[3] ^= s[1]; s[1] ^= s[2]; s[0] ^= s[3];
    s[2] ^= t;    s[3] = fu_rotl(s[3], 45);
    return r;
}

/* ------------------------------------------------------------------ */
/* generators                                                         */
/* ------------------------------------------------------------------ */

static void fu_gen_v4(unsigned char *b) {
    fu_rand(b, 16);
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
}

static void fu_gen_v4_fast(unsigned char *b) {
    uint64_t a = fu_xs_next(), c = fu_xs_next();
    memcpy(b, &a, 8); memcpy(b + 8, &c, 8);
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
}

/* lay out a v1 UUID from a Gregorian 100ns timestamp; node (6 bytes) and
   clockseq (0..0x3fff) optional — pass NULL / -1 to randomize them. */
static void fu_lay_v1(unsigned char *b, uint64_t g, const unsigned char *node, int clockseq) {
    uint32_t tl = (uint32_t)(g & 0xffffffffULL);
    uint16_t tm = (uint16_t)((g >> 32) & 0xffff);
    uint16_t th = (uint16_t)((g >> 48) & 0x0fff) | 0x1000; /* version 1 */
    b[0]=tl>>24; b[1]=tl>>16; b[2]=tl>>8; b[3]=tl;
    b[4]=tm>>8;  b[5]=tm;
    b[6]=th>>8;  b[7]=th;
    if (clockseq < 0) { unsigned char r[2]; fu_rand(r, 2); clockseq = ((r[0]<<8)|r[1]) & 0x3fff; }
    b[8]=((clockseq>>8) & 0x3f) | 0x80;  /* variant + clock_seq_hi */
    b[9]=clockseq & 0xff;                /* clock_seq_low */
    if (node) {
        memcpy(b+10, node, 6);
    } else {
        unsigned char r[6]; fu_rand(r, 6);
        memcpy(b+10, r, 6);
        b[10] |= 0x01;                   /* multicast bit: random node */
    }
}

static inline uint64_t fu_greg_now(void) {
    struct timespec ts;
    clock_gettime(CLOCK_REALTIME, &ts);
    uint64_t unix100 = (uint64_t)ts.tv_sec * 10000000ULL + (uint64_t)ts.tv_nsec / 100ULL;
    return unix100 + 0x01B21DD213814000ULL; /* 1582-10-15 .. 1970 in 100ns */
}

static void fu_gen_v1_ex(unsigned char *b, const unsigned char *node, int clockseq) {
    fu_lay_v1(b, fu_greg_now(), node, clockseq);
}

static void fu_gen_v1(unsigned char *b) { fu_gen_v1_ex(b, NULL, -1); }

static void fu_gen_v6_ex(unsigned char *b, const unsigned char *node, int clockseq) {
    unsigned char v1[16];
    fu_gen_v1_ex(v1, node, clockseq);
    uint64_t th = ((uint64_t)(v1[6] & 0x0f) << 8) | v1[7];
    uint64_t tm = ((uint64_t)v1[4] << 8) | v1[5];
    uint64_t tl = ((uint64_t)v1[0] << 24) | ((uint64_t)v1[1] << 16) | ((uint64_t)v1[2] << 8) | v1[3];
    uint64_t t60 = (th << 48) | (tm << 32) | tl;
    b[0]=(t60>>52)&0xff; b[1]=(t60>>44)&0xff; b[2]=(t60>>36)&0xff; b[3]=(t60>>28)&0xff;
    b[4]=(t60>>20)&0xff; b[5]=(t60>>12)&0xff;
    b[6]=0x60 | ((t60>>8)&0x0f);
    b[7]=t60 & 0xff;
    memcpy(b+8, v1+8, 8); /* variant + clock_seq + node preserved */
}

static void fu_gen_v6(unsigned char *b) { fu_gen_v6_ex(b, NULL, -1); }

/* DCE Security (v2): v1 time layout, but time_low := local identifier and
   clock_seq_low := local domain; version nibble = 2. The low 32 timestamp bits
   are sacrificed for the local id, so v2 time resolution is coarse. */
static void fu_gen_v2(unsigned char *b, uint32_t local_id, unsigned char local_domain,
                      const unsigned char *node, int clockseq) {
    fu_lay_v1(b, fu_greg_now(), node, clockseq);
    b[0] = (local_id >> 24) & 0xff; b[1] = (local_id >> 16) & 0xff;
    b[2] = (local_id >> 8)  & 0xff; b[3] = local_id & 0xff;
    b[6] = (b[6] & 0x0f) | 0x20;    /* version 2 */
    b[9] = local_domain;            /* clock_seq_low := local domain */
}

static void fu_lay_v7(unsigned char *b, uint64_t ms, uint64_t counter) {
    b[0]=(ms>>40)&0xff; b[1]=(ms>>32)&0xff; b[2]=(ms>>24)&0xff;
    b[3]=(ms>>16)&0xff; b[4]=(ms>>8)&0xff;  b[5]=ms&0xff;
    b[6]=0x70 | ((counter>>38)&0x0f);
    b[7]=(counter>>30)&0xff;
    b[8]=0x80 | ((counter>>24)&0x3f);
    b[9]=(counter>>16)&0xff; b[10]=(counter>>8)&0xff; b[11]=counter&0xff;
    unsigned char r[4]; fu_rand(r, 4); memcpy(b+12, r, 4);
}

/* monotonic v7 using module-global state */
static void fu_gen_v7(unsigned char *b) {
    struct timespec ts;
    clock_gettime(CLOCK_REALTIME, &ts);
    uint64_t ms = (uint64_t)ts.tv_sec * 1000ULL + (uint64_t)ts.tv_nsec / 1000000ULL;
    uint64_t counter;
    if (ms > FAST_UUID_G(v7_ts)) {
        FAST_UUID_G(v7_ts) = ms;
        unsigned char r[6]; fu_rand(r, 6);
        counter = (((uint64_t)r[0]<<34)|((uint64_t)r[1]<<26)|((uint64_t)r[2]<<18)
                  |((uint64_t)r[3]<<10)|((uint64_t)r[4]<<2)|((uint64_t)r[5]>>6));
        counter &= (1ULL<<40) - 1; /* leave 2 high bits as overflow headroom */
        FAST_UUID_G(v7_counter) = counter;
    } else {
        counter = FAST_UUID_G(v7_counter) + 1;
        if (counter > ((1ULL<<42)-1)) { FAST_UUID_G(v7_ts)++; counter = 0; }
        FAST_UUID_G(v7_counter) = counter;
        ms = FAST_UUID_G(v7_ts);
    }
    fu_lay_v7(b, ms, counter);
}

/* non-monotonic v7 at an explicit timestamp */
static void fu_gen_v7_at(unsigned char *b, uint64_t ms) {
    unsigned char r[6]; fu_rand(r, 6);
    uint64_t counter = (((uint64_t)r[0]<<34)|((uint64_t)r[1]<<26)|((uint64_t)r[2]<<18)
                       |((uint64_t)r[3]<<10)|((uint64_t)r[4]<<2)|((uint64_t)r[5]>>6)) & ((1ULL<<42)-1);
    fu_lay_v7(b, ms, counter);
}

static void fu_gen_v3(unsigned char *b, const unsigned char ns[16], const char *name, size_t nl) {
    PHP_MD5_CTX c; unsigned char d[16];
    PHP_MD5Init(&c);
    PHP_MD5Update(&c, ns, 16);
    PHP_MD5Update(&c, (const unsigned char *)name, nl);
    PHP_MD5Final(d, &c);
    memcpy(b, d, 16);
    b[6] = (b[6] & 0x0f) | 0x30;
    b[8] = (b[8] & 0x3f) | 0x80;
}

static void fu_gen_v5(unsigned char *b, const unsigned char ns[16], const char *name, size_t nl) {
    PHP_SHA1_CTX c; unsigned char d[20];
    PHP_SHA1Init(&c);
    PHP_SHA1Update(&c, ns, 16);
    PHP_SHA1Update(&c, (const unsigned char *)name, nl);
    PHP_SHA1Final(d, &c);
    memcpy(b, d, 16);
    b[6] = (b[6] & 0x0f) | 0x50;
    b[8] = (b[8] & 0x3f) | 0x80;
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

static void fu_return_uuid(zval *rv, const unsigned char b[16]) {
    object_init_ex(rv, fast_uuid_ce);
    fu_obj *u = fu_from_zobj(Z_OBJ_P(rv));
    memcpy(u->b, b, 16);
}

static int fu_resolve_ns(zval *z, unsigned char ns[16]) {
    if (Z_TYPE_P(z) == IS_OBJECT && instanceof_function(Z_OBJCE_P(z), fast_uuid_ce)) {
        memcpy(ns, fu_from_zobj(Z_OBJ_P(z))->b, 16);
        return 1;
    }
    if (Z_TYPE_P(z) == IS_STRING) return fu_parse(Z_STRVAL_P(z), Z_STRLEN_P(z), ns);
    return 0;
}

/* ramsey-shaped exception hierarchy (parse failures throw the string variant) */
static zend_class_entry *fu_ex_invalid_arg;
static zend_class_entry *fu_ex_invalid_str;
static zend_class_entry *fu_ex_unsupported;

/* parse a node override: 12-hex string, or int (low 48 bits). returns 1 on success */
static int fu_parse_node(zval *z, unsigned char node[6]) {
    if (Z_TYPE_P(z) == IS_LONG) {
        uint64_t n = (uint64_t)Z_LVAL_P(z);
        for (int i = 0; i < 6; i++) node[i] = (n >> (8 * (5 - i))) & 0xff;
        return 1;
    }
    if (Z_TYPE_P(z) == IS_STRING) {
        const char *s = Z_STRVAL_P(z); size_t l = Z_STRLEN_P(z);
        if (l != 12) return 0;
        for (int i = 0; i < 6; i++) {
            int hi = fu_nib(s[i*2]), lo = fu_nib(s[i*2+1]);
            if (hi < 0 || lo < 0) return 0;
            node[i] = (hi << 4) | lo;
        }
        return 1;
    }
    return 0;
}

/* build a DateTimeImmutable from whole seconds + microseconds, preserving sub-second */
static void fu_make_datetime(zval *rv, int64_t secs, uint32_t micros) {
    zend_string *buf = strpprintf(0, "%lld.%06u", (long long)secs, micros);
    zval callable, args[2], ret;
    array_init(&callable);
    add_next_index_string(&callable, "DateTimeImmutable");
    add_next_index_string(&callable, "createFromFormat");
    ZVAL_STRING(&args[0], "U.u");
    ZVAL_STR(&args[1], buf); /* transfers buf ref */
    if (call_user_function(NULL, NULL, &callable, &ret, 2, args) == SUCCESS && Z_TYPE(ret) == IS_OBJECT) {
        ZVAL_COPY_VALUE(rv, &ret);
    } else {
        zval_ptr_dtor(&ret);
        ZVAL_NULL(rv);
    }
    zval_ptr_dtor(&callable);
    zval_ptr_dtor(&args[0]);
    zval_ptr_dtor(&args[1]);
}

/* arginfo, method/function tables, and class registrators are generated from
   fast_uuid.stub.php into fast_uuid_arginfo.h (included near the top of this
   file). Regenerate with /php-stub-regen after any signature change. */

/* ------------------------------------------------------------------ */
/* static factories                                                   */
/* ------------------------------------------------------------------ */

PHP_METHOD(FastUuid_Uuid, uuid1) {
    zval *znode = NULL; zend_long clockseq = -1; zend_bool cs_null = 1;
    ZEND_PARSE_PARAMETERS_START(0, 2)
        Z_PARAM_OPTIONAL
        Z_PARAM_ZVAL_OR_NULL(znode)
        Z_PARAM_LONG_OR_NULL(clockseq, cs_null)
    ZEND_PARSE_PARAMETERS_END();
    unsigned char node[6]; const unsigned char *np = NULL;
    if (znode && Z_TYPE_P(znode) != IS_NULL) {
        if (!fu_parse_node(znode, node)) { zend_throw_exception(fu_ex_invalid_arg, "Invalid node (expect 12-hex string or int)", 0); RETURN_THROWS(); }
        np = node;
    }
    unsigned char b[16]; fu_gen_v1_ex(b, np, cs_null ? -1 : (int)(clockseq & 0x3fff)); fu_return_uuid(return_value, b);
}

PHP_METHOD(FastUuid_Uuid, uuid2) {
    zend_long local_domain;
    zval *zid = NULL, *znode = NULL; zend_long clockseq = -1; zend_bool cs_null = 1;
    ZEND_PARSE_PARAMETERS_START(1, 4)
        Z_PARAM_LONG(local_domain)
        Z_PARAM_OPTIONAL
        Z_PARAM_ZVAL_OR_NULL(zid)
        Z_PARAM_ZVAL_OR_NULL(znode)
        Z_PARAM_LONG_OR_NULL(clockseq, cs_null)
    ZEND_PARSE_PARAMETERS_END();
    if (local_domain < 0 || local_domain > 0xff) {
        zend_throw_exception(fu_ex_invalid_arg, "localDomain must be 0..255 (PERSON=0, GROUP=1, ORG=2)", 0);
        RETURN_THROWS();
    }
    uint32_t local_id;
    if (zid && Z_TYPE_P(zid) != IS_NULL) {
        if (Z_TYPE_P(zid) == IS_LONG)        local_id = (uint32_t)Z_LVAL_P(zid);
        else if (Z_TYPE_P(zid) == IS_STRING) local_id = (uint32_t)strtoull(Z_STRVAL_P(zid), NULL, 10);
        else { zend_throw_exception(fu_ex_invalid_arg, "localIdentifier must be int|string|null", 0); RETURN_THROWS(); }
    } else {
#ifndef PHP_WIN32
        local_id = (local_domain == 1) ? (uint32_t)getgid() : (uint32_t)getuid();
#else
        local_id = 0;
#endif
    }
    unsigned char node[6]; const unsigned char *np = NULL;
    if (znode && Z_TYPE_P(znode) != IS_NULL) {
        if (!fu_parse_node(znode, node)) { zend_throw_exception(fu_ex_invalid_arg, "Invalid node", 0); RETURN_THROWS(); }
        np = node;
    }
    unsigned char b[16];
    fu_gen_v2(b, local_id, (unsigned char)local_domain, np, cs_null ? -1 : (int)(clockseq & 0x3fff));
    fu_return_uuid(return_value, b);
}

PHP_METHOD(FastUuid_Uuid, uuid3) {
    zval *zns; zend_string *name;
    ZEND_PARSE_PARAMETERS_START(2, 2) Z_PARAM_ZVAL(zns) Z_PARAM_STR(name) ZEND_PARSE_PARAMETERS_END();
    unsigned char ns[16];
    if (!fu_resolve_ns(zns, ns)) { zend_throw_exception(fu_ex_invalid_arg, "Invalid namespace", 0); RETURN_THROWS(); }
    unsigned char b[16]; fu_gen_v3(b, ns, ZSTR_VAL(name), ZSTR_LEN(name)); fu_return_uuid(return_value, b);
}

PHP_METHOD(FastUuid_Uuid, uuid4) {
    ZEND_PARSE_PARAMETERS_NONE();
    unsigned char b[16]; fu_gen_v4(b); fu_return_uuid(return_value, b);
}

PHP_METHOD(FastUuid_Uuid, uuid5) {
    zval *zns; zend_string *name;
    ZEND_PARSE_PARAMETERS_START(2, 2) Z_PARAM_ZVAL(zns) Z_PARAM_STR(name) ZEND_PARSE_PARAMETERS_END();
    unsigned char ns[16];
    if (!fu_resolve_ns(zns, ns)) { zend_throw_exception(fu_ex_invalid_arg, "Invalid namespace", 0); RETURN_THROWS(); }
    unsigned char b[16]; fu_gen_v5(b, ns, ZSTR_VAL(name), ZSTR_LEN(name)); fu_return_uuid(return_value, b);
}

PHP_METHOD(FastUuid_Uuid, uuid6) {
    zval *znode = NULL; zend_long clockseq = -1; zend_bool cs_null = 1;
    ZEND_PARSE_PARAMETERS_START(0, 2)
        Z_PARAM_OPTIONAL
        Z_PARAM_ZVAL_OR_NULL(znode)
        Z_PARAM_LONG_OR_NULL(clockseq, cs_null)
    ZEND_PARSE_PARAMETERS_END();
    unsigned char node[6]; const unsigned char *np = NULL;
    if (znode && Z_TYPE_P(znode) != IS_NULL) {
        if (!fu_parse_node(znode, node)) { zend_throw_exception(fu_ex_invalid_arg, "Invalid node (expect 12-hex string or int)", 0); RETURN_THROWS(); }
        np = node;
    }
    unsigned char b[16]; fu_gen_v6_ex(b, np, cs_null ? -1 : (int)(clockseq & 0x3fff)); fu_return_uuid(return_value, b);
}

PHP_METHOD(FastUuid_Uuid, uuid7) {
    zval *zdt = NULL;
    ZEND_PARSE_PARAMETERS_START(0, 1) Z_PARAM_OPTIONAL Z_PARAM_OBJECT_OR_NULL(zdt) ZEND_PARSE_PARAMETERS_END();
    unsigned char b[16];
    if (zdt) {
        zval fn, ret;
        ZVAL_STRING(&fn, "getTimestamp");
        call_user_function(NULL, zdt, &fn, &ret, 0, NULL);
        zend_long sec = zval_get_long(&ret);
        zval_ptr_dtor(&ret); zval_ptr_dtor(&fn);
        fu_gen_v7_at(b, (uint64_t)sec * 1000ULL);
    } else {
        fu_gen_v7(b);
    }
    fu_return_uuid(return_value, b);
}

PHP_METHOD(FastUuid_Uuid, uuid8) {
    zend_string *data;
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_STR(data) ZEND_PARSE_PARAMETERS_END();
    if (ZSTR_LEN(data) != 16) { zend_throw_exception(fu_ex_invalid_arg, "uuid8 requires 16 bytes", 0); RETURN_THROWS(); }
    unsigned char b[16]; memcpy(b, ZSTR_VAL(data), 16);
    b[6] = (b[6] & 0x0f) | 0x80;
    b[8] = (b[8] & 0x3f) | 0x80;
    fu_return_uuid(return_value, b);
}

PHP_METHOD(FastUuid_Uuid, fromString) {
    zend_string *s;
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_STR(s) ZEND_PARSE_PARAMETERS_END();
    unsigned char b[16];
    if (!fu_parse(ZSTR_VAL(s), ZSTR_LEN(s), b)) { zend_throw_exception_ex(fu_ex_invalid_str, 0, "Invalid UUID string: %s", ZSTR_VAL(s)); RETURN_THROWS(); }
    fu_return_uuid(return_value, b);
}

PHP_METHOD(FastUuid_Uuid, fromBytes) {
    zend_string *s;
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_STR(s) ZEND_PARSE_PARAMETERS_END();
    if (ZSTR_LEN(s) != 16) { zend_throw_exception(fu_ex_invalid_arg, "UUID bytes must be 16 long", 0); RETURN_THROWS(); }
    fu_return_uuid(return_value, (const unsigned char *)ZSTR_VAL(s));
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
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_STR(s) ZEND_PARSE_PARAMETERS_END();
    unsigned char b[16];
    RETURN_BOOL(fu_parse(ZSTR_VAL(s), ZSTR_LEN(s), b));
}

PHP_METHOD(FastUuid_Uuid, fromHexadecimal) {
    zval *z; zend_string *s;
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_ZVAL(z) ZEND_PARSE_PARAMETERS_END();
    /* accept a 32-char hex string or any object stringifiable to one (ramsey Type\Hexadecimal) */
    if (Z_TYPE_P(z) == IS_STRING) { s = Z_STR_P(z); zend_string_addref(s); }
    else if (Z_TYPE_P(z) == IS_OBJECT) { zval t; ZVAL_OBJ(&t, Z_OBJ_P(z)); s = zval_get_string(&t); }
    else { zend_throw_exception(fu_ex_invalid_arg, "Expected hexadecimal string", 0); RETURN_THROWS(); }
    unsigned char b[16];
    int ok = (ZSTR_LEN(s) == 32) && fu_parse(ZSTR_VAL(s), 32, b);
    zend_string_release(s);
    if (!ok) { zend_throw_exception(fu_ex_invalid_str, "Invalid hexadecimal UUID (expect 32 hex chars)", 0); RETURN_THROWS(); }
    fu_return_uuid(return_value, b);
}

PHP_METHOD(FastUuid_Uuid, fromDateTime) {
    zval *zdt; zval *zclock = NULL; zval *znode = NULL;
    ZEND_PARSE_PARAMETERS_START(1, 3)
        Z_PARAM_OBJECT(zdt)
        Z_PARAM_OPTIONAL
        Z_PARAM_ZVAL_OR_NULL(znode)
        Z_PARAM_ZVAL_OR_NULL(zclock) /* tolerated, ignored: signature parity */
    ZEND_PARSE_PARAMETERS_END();
    (void)zclock;
    /* read seconds + microseconds off the DateTimeInterface */
    zval fn, ret;
    ZVAL_STRING(&fn, "getTimestamp");
    call_user_function(NULL, zdt, &fn, &ret, 0, NULL);
    int64_t secs = (int64_t)zval_get_long(&ret);
    zval_ptr_dtor(&ret); zval_ptr_dtor(&fn);
    zval afmt; ZVAL_STRING(&fn, "format"); ZVAL_STRING(&afmt, "u");
    call_user_function(NULL, zdt, &fn, &ret, 1, &afmt);
    uint32_t micros = (uint32_t)zval_get_long(&ret);
    zval_ptr_dtor(&ret); zval_ptr_dtor(&fn); zval_ptr_dtor(&afmt);

    unsigned char node[6]; const unsigned char *np = NULL;
    if (znode && Z_TYPE_P(znode) != IS_NULL) {
        if (!fu_parse_node(znode, node)) { zend_throw_exception(fu_ex_invalid_arg, "Invalid node", 0); RETURN_THROWS(); }
        np = node;
    }
    uint64_t g = (uint64_t)secs * 10000000ULL + (uint64_t)micros * 10ULL + 0x01B21DD213814000ULL;
    unsigned char b[16]; fu_lay_v1(b, g, np, -1);
    fu_return_uuid(return_value, b);
}

/* ------------------------------------------------------------------ */
/* instance methods                                                   */
/* ------------------------------------------------------------------ */

PHP_METHOD(FastUuid_Uuid, __construct) {
    /* intentionally private: use factory methods */
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
    fu_format32(u->b, ZSTR_VAL(s)); ZSTR_VAL(s)[32] = '\0';
    RETURN_STR(s);
}

PHP_METHOD(FastUuid_Uuid, getUrn) {
    ZEND_PARSE_PARAMETERS_NONE();
    fu_obj *u = fu_from_zobj(Z_OBJ_P(getThis()));
    zend_string *s = zend_string_alloc(45, 0);
    memcpy(ZSTR_VAL(s), "urn:uuid:", 9);
    fu_format36(u->b, ZSTR_VAL(s) + 9); ZSTR_VAL(s)[45] = '\0';
    RETURN_STR(s);
}

PHP_METHOD(FastUuid_Uuid, getVersion) {
    ZEND_PARSE_PARAMETERS_NONE();
    fu_obj *u = fu_from_zobj(Z_OBJ_P(getThis()));
    int allz = 1, allf = 1;
    for (int i = 0; i < 16; i++) { if (u->b[i]) allz = 0; if (u->b[i] != 0xff) allf = 0; }
    if (allz || allf) RETURN_NULL();
    RETURN_LONG((u->b[6] >> 4) & 0x0f);
}

PHP_METHOD(FastUuid_Uuid, getVariant) {
    ZEND_PARSE_PARAMETERS_NONE();
    fu_obj *u = fu_from_zobj(Z_OBJ_P(getThis()));
    unsigned char o = u->b[8];
    if ((o & 0x80) == 0x00)      RETURN_LONG(0); /* NCS */
    else if ((o & 0xC0) == 0x80) RETURN_LONG(2); /* RFC 4122 */
    else if ((o & 0xE0) == 0xC0) RETURN_LONG(6); /* Microsoft */
    else                         RETURN_LONG(7); /* future */
}

PHP_METHOD(FastUuid_Uuid, getInteger) {
    ZEND_PARSE_PARAMETERS_NONE();
    fu_obj *u = fu_from_zobj(Z_OBJ_P(getThis()));
    RETURN_STR(fu_to_decimal(u->b)); /* numeric string (ramsey wraps in IntegerObject) */
}

PHP_METHOD(FastUuid_Uuid, getDateTime) {
    ZEND_PARSE_PARAMETERS_NONE();
    fu_obj *u = fu_from_zobj(Z_OBJ_P(getThis()));
    int ver = (u->b[6] >> 4) & 0x0f;
    int64_t secs = 0; uint32_t micros = 0;
    if (ver == 1 || ver == 6) {
        uint64_t t60;
        if (ver == 1) {
            uint64_t th = ((uint64_t)(u->b[6]&0x0f)<<8)|u->b[7];
            uint64_t tm = ((uint64_t)u->b[4]<<8)|u->b[5];
            uint64_t tl = ((uint64_t)u->b[0]<<24)|((uint64_t)u->b[1]<<16)|((uint64_t)u->b[2]<<8)|u->b[3];
            t60 = (th<<48)|(tm<<32)|tl;
        } else {
            t60 = ((uint64_t)u->b[0]<<52)|((uint64_t)u->b[1]<<44)|((uint64_t)u->b[2]<<36)
                 |((uint64_t)u->b[3]<<28)|((uint64_t)u->b[4]<<20)|((uint64_t)u->b[5]<<12)
                 |((uint64_t)(u->b[6]&0x0f)<<8)|u->b[7];
        }
        uint64_t unix100 = t60 - 0x01B21DD213814000ULL; /* 100ns since epoch */
        secs   = (int64_t)(unix100 / 10000000ULL);
        micros = (uint32_t)((unix100 % 10000000ULL) / 10ULL); /* 100ns -> us */
    } else if (ver == 2) {
        /* DCE v2: time_low holds the local identifier, so only the upper bits
           of the 60-bit timestamp survive (coarse, ~429s resolution). */
        uint64_t th = ((uint64_t)(u->b[6]&0x0f)<<8)|u->b[7];
        uint64_t tm = ((uint64_t)u->b[4]<<8)|u->b[5];
        uint64_t unix100 = ((th<<48)|(tm<<32)) - 0x01B21DD213814000ULL;
        secs   = (int64_t)(unix100 / 10000000ULL);
        micros = (uint32_t)((unix100 % 10000000ULL) / 10ULL);
    } else if (ver == 7) {
        uint64_t ms = ((uint64_t)u->b[0]<<40)|((uint64_t)u->b[1]<<32)|((uint64_t)u->b[2]<<24)
                     |((uint64_t)u->b[3]<<16)|((uint64_t)u->b[4]<<8)|u->b[5];
        secs   = (int64_t)(ms / 1000ULL);
        micros = (uint32_t)((ms % 1000ULL) * 1000ULL);
    } else {
        zend_throw_exception(fu_ex_unsupported, "UUID has no embedded timestamp", 0);
        RETURN_THROWS();
    }
    fu_make_datetime(return_value, secs, micros);
}

PHP_METHOD(FastUuid_Uuid, getFields) {
    /* NOTE: ramsey returns a FieldsInterface object; scaffold returns a hex array. */
    ZEND_PARSE_PARAMETERS_NONE();
    fu_obj *u = fu_from_zobj(Z_OBJ_P(getThis()));
    array_init(return_value);
    char tl[9]; for (int i=0;i<4;i++){tl[i*2]=fu_lut[u->b[i]*2];tl[i*2+1]=fu_lut[u->b[i]*2+1];} tl[8]=0;
    add_assoc_string(return_value, "time_low", tl);
    char tm[5]; for (int i=0;i<2;i++){tm[i*2]=fu_lut[u->b[4+i]*2];tm[i*2+1]=fu_lut[u->b[4+i]*2+1];} tm[4]=0;
    add_assoc_string(return_value, "time_mid", tm);
    char th[5]; for (int i=0;i<2;i++){th[i*2]=fu_lut[u->b[6+i]*2];th[i*2+1]=fu_lut[u->b[6+i]*2+1];} th[4]=0;
    add_assoc_string(return_value, "time_hi_and_version", th);
    char cs[3]; cs[0]=fu_lut[u->b[8]*2]; cs[1]=fu_lut[u->b[8]*2+1]; cs[2]=0;
    add_assoc_string(return_value, "clock_seq_hi_and_reserved", cs);
    char cl[3]; cl[0]=fu_lut[u->b[9]*2]; cl[1]=fu_lut[u->b[9]*2+1]; cl[2]=0;
    add_assoc_string(return_value, "clock_seq_low", cl);
    char nd[13]; for (int i=0;i<6;i++){nd[i*2]=fu_lut[u->b[10+i]*2];nd[i*2+1]=fu_lut[u->b[10+i]*2+1];} nd[12]=0;
    add_assoc_string(return_value, "node", nd);
}

PHP_METHOD(FastUuid_Uuid, equals) {
    zval *o;
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_ZVAL(o) ZEND_PARSE_PARAMETERS_END();
    fu_obj *self = fu_from_zobj(Z_OBJ_P(getThis()));
    unsigned char b[16];
    if (Z_TYPE_P(o) == IS_OBJECT && instanceof_function(Z_OBJCE_P(o), fast_uuid_ce))
        memcpy(b, fu_from_zobj(Z_OBJ_P(o))->b, 16);
    else if (Z_TYPE_P(o) == IS_STRING && fu_parse(Z_STRVAL_P(o), Z_STRLEN_P(o), b)) {}
    else RETURN_FALSE;
    RETURN_BOOL(memcmp(self->b, b, 16) == 0);
}

PHP_METHOD(FastUuid_Uuid, compareTo) {
    zval *o;
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_ZVAL(o) ZEND_PARSE_PARAMETERS_END();
    fu_obj *self = fu_from_zobj(Z_OBJ_P(getThis()));
    unsigned char b[16];
    if (Z_TYPE_P(o) == IS_OBJECT && instanceof_function(Z_OBJCE_P(o), fast_uuid_ce))
        memcpy(b, fu_from_zobj(Z_OBJ_P(o))->b, 16);
    else if (Z_TYPE_P(o) == IS_STRING && fu_parse(Z_STRVAL_P(o), Z_STRLEN_P(o), b)) {}
    else { zend_throw_exception(fu_ex_invalid_arg, "Not comparable", 0); RETURN_THROWS(); }
    int r = memcmp(self->b, b, 16);
    RETURN_LONG(r < 0 ? -1 : (r > 0 ? 1 : 0));
}

PHP_METHOD(FastUuid_Uuid, jsonSerialize) {
    ZEND_PARSE_PARAMETERS_NONE();
    RETURN_STR(fu_str(fu_from_zobj(Z_OBJ_P(getThis()))));
}

/* ------------------------------------------------------------------ */
/* procedural fast-path (zend_string return, no object alloc)         */
/* ------------------------------------------------------------------ */

#define FU_RETURN_FORMATTED(b) do { \
    const unsigned char *_fu_src = (b); \
    zend_string *_fu_s = zend_string_alloc(36, 0); \
    fu_format36(_fu_src, ZSTR_VAL(_fu_s)); ZSTR_VAL(_fu_s)[36] = '\0'; \
    RETURN_STR(_fu_s); } while (0)

PHP_FUNCTION(uuid_v1) { ZEND_PARSE_PARAMETERS_NONE(); unsigned char b[16]; fu_gen_v1(b); FU_RETURN_FORMATTED(b); }
PHP_FUNCTION(uuid_v4) { ZEND_PARSE_PARAMETERS_NONE(); unsigned char b[16]; fu_gen_v4(b); FU_RETURN_FORMATTED(b); }
PHP_FUNCTION(uuid_v4_fast) { ZEND_PARSE_PARAMETERS_NONE(); unsigned char b[16]; fu_gen_v4_fast(b); FU_RETURN_FORMATTED(b); }
PHP_FUNCTION(uuid_v6) { ZEND_PARSE_PARAMETERS_NONE(); unsigned char b[16]; fu_gen_v6(b); FU_RETURN_FORMATTED(b); }
PHP_FUNCTION(uuid_v7) { ZEND_PARSE_PARAMETERS_NONE(); unsigned char b[16]; fu_gen_v7(b); FU_RETURN_FORMATTED(b); }

PHP_FUNCTION(uuid_v3) {
    zend_string *zns, *name;
    ZEND_PARSE_PARAMETERS_START(2, 2) Z_PARAM_STR(zns) Z_PARAM_STR(name) ZEND_PARSE_PARAMETERS_END();
    unsigned char ns[16], b[16];
    if (!fu_parse(ZSTR_VAL(zns), ZSTR_LEN(zns), ns)) { zend_throw_exception(fu_ex_invalid_arg, "Invalid namespace", 0); RETURN_THROWS(); }
    fu_gen_v3(b, ns, ZSTR_VAL(name), ZSTR_LEN(name)); FU_RETURN_FORMATTED(b);
}

PHP_FUNCTION(uuid_v5) {
    zend_string *zns, *name;
    ZEND_PARSE_PARAMETERS_START(2, 2) Z_PARAM_STR(zns) Z_PARAM_STR(name) ZEND_PARSE_PARAMETERS_END();
    unsigned char ns[16], b[16];
    if (!fu_parse(ZSTR_VAL(zns), ZSTR_LEN(zns), ns)) { zend_throw_exception(fu_ex_invalid_arg, "Invalid namespace", 0); RETURN_THROWS(); }
    fu_gen_v5(b, ns, ZSTR_VAL(name), ZSTR_LEN(name)); FU_RETURN_FORMATTED(b);
}

PHP_FUNCTION(uuid_v8) {
    zend_string *data;
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_STR(data) ZEND_PARSE_PARAMETERS_END();
    if (ZSTR_LEN(data) != 16) { zend_throw_exception(fu_ex_invalid_arg, "uuid8 requires 16 bytes", 0); RETURN_THROWS(); }
    unsigned char b[16]; memcpy(b, ZSTR_VAL(data), 16);
    b[6] = (b[6] & 0x0f) | 0x80; b[8] = (b[8] & 0x3f) | 0x80;
    FU_RETURN_FORMATTED(b);
}

PHP_FUNCTION(uuid_to_bin) {
    zend_string *s;
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_STR(s) ZEND_PARSE_PARAMETERS_END();
    unsigned char b[16];
    if (!fu_parse(ZSTR_VAL(s), ZSTR_LEN(s), b)) { zend_throw_exception(fu_ex_invalid_arg, "Invalid UUID", 0); RETURN_THROWS(); }
    RETURN_STRINGL((char *)b, 16);
}

PHP_FUNCTION(uuid_from_bin) {
    zend_string *s;
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_STR(s) ZEND_PARSE_PARAMETERS_END();
    if (ZSTR_LEN(s) != 16) { zend_throw_exception(fu_ex_invalid_arg, "Expected 16 bytes", 0); RETURN_THROWS(); }
    FU_RETURN_FORMATTED((const unsigned char *)ZSTR_VAL(s));
}

PHP_FUNCTION(uuid_is_valid) {
    zend_string *s;
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_STR(s) ZEND_PARSE_PARAMETERS_END();
    unsigned char b[16];
    RETURN_BOOL(fu_parse(ZSTR_VAL(s), ZSTR_LEN(s), b));
}

/* raw fast random bytes (handy for a ramsey RandomGeneratorInterface adapter) */
PHP_FUNCTION(fast_uuid_random_bytes) {
    zend_long n;
    ZEND_PARSE_PARAMETERS_START(1, 1) Z_PARAM_LONG(n) ZEND_PARSE_PARAMETERS_END();
    if (n <= 0) { zend_throw_exception(fu_ex_invalid_arg, "length must be > 0", 0); RETURN_THROWS(); }
    zend_string *s = zend_string_alloc((size_t)n, 0);
    fu_rand((unsigned char *)ZSTR_VAL(s), (size_t)n);
    ZSTR_VAL(s)[n] = '\0';
    RETURN_STR(s);
}

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

PHP_MINIT_FUNCTION(fast_uuid) {
    for (int i = 0; i < 256; i++) { fu_lut[i*2] = fu_hexd[i >> 4]; fu_lut[i*2+1] = fu_hexd[i & 0x0f]; }

#ifdef FU_X86
    __builtin_cpu_init();
    fu_has_ssse3 = __builtin_cpu_supports("ssse3");
#endif

    fast_uuid_iface_ce = register_class_FastUuid_UuidInterface(php_json_serializable_ce, zend_ce_stringable);
    fast_uuid_ce = register_class_FastUuid_Uuid(fast_uuid_iface_ce);
    fast_uuid_ce->create_object = fu_create;

    memcpy(&fu_handlers, zend_get_std_object_handlers(), sizeof(zend_object_handlers));
    fu_handlers.offset      = offsetof(fu_obj, std);
    fu_handlers.free_obj    = fu_free;
    fu_handlers.clone_obj   = fu_clone;
    fu_handlers.compare     = fu_compare;
    fu_handlers.cast_object = fu_cast;

    /* exception hierarchy (ramsey-shaped): InvalidUuidString <- InvalidArgument <- \InvalidArgumentException */
    fu_ex_invalid_arg = register_class_FastUuid_Exception_InvalidArgumentException(spl_ce_InvalidArgumentException);
    fu_ex_invalid_str = register_class_FastUuid_Exception_InvalidUuidStringException(fu_ex_invalid_arg);
    fu_ex_unsupported = register_class_FastUuid_Exception_UnsupportedOperationException(spl_ce_RuntimeException);

    return SUCCESS;
}

PHP_MINFO_FUNCTION(fast_uuid) {
    php_info_print_table_start();
    php_info_print_table_row(2, "fast_uuid support", "enabled");
    php_info_print_table_row(2, "version", PHP_FAST_UUID_VERSION);
#ifdef FU_X86
    php_info_print_table_row(2, "x86 SIMD hex formatter", fu_has_ssse3 ? "SSSE3" : "scalar (CPU lacks SSSE3)");
#else
    php_info_print_table_row(2, "x86 SIMD hex formatter", "n/a (non-x86 build)");
#endif
    php_info_print_table_end();
}

zend_module_entry fast_uuid_module_entry = {
    STANDARD_MODULE_HEADER,
    "fast_uuid",
    ext_functions,
    PHP_MINIT(fast_uuid),
    NULL,
    NULL,
    NULL,
    PHP_MINFO(fast_uuid),
    PHP_FAST_UUID_VERSION,
    PHP_MODULE_GLOBALS(fast_uuid),
    PHP_GINIT(fast_uuid),
    NULL,
    NULL,
    STANDARD_MODULE_PROPERTIES_EX
};

#ifdef COMPILE_DL_FAST_UUID
# ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
# endif
ZEND_GET_MODULE(fast_uuid)
#endif
