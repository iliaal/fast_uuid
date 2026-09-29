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

#ifndef PHP_FAST_UUID_H
#define PHP_FAST_UUID_H

#include <stdint.h>

extern zend_module_entry fast_uuid_module_entry;
#define phpext_fast_uuid_ptr &fast_uuid_module_entry

#define PHP_FAST_UUID_VERSION "0.8.0"

#ifdef ZTS
#include "TSRM.h"
#endif

ZEND_BEGIN_MODULE_GLOBALS(fast_uuid)
    /* batched CSPRNG buffer: refilled by the AES-256-CTR DRBG when the CPU has
       AES instructions, otherwise by the OS CSPRNG (php_random_bytes) */
    unsigned char rbuf[8192];
    size_t        rpos;
    /* AES-256-CTR DRBG: drbg_key is overwritten after every generate call
       (fast key erasure); round keys only ever live on the stack */
    unsigned char drbg_key[32];
    unsigned char drbg_ctr[16];
    size_t        drbg_since_seed; /* output bytes since the last OS reseed */
    zend_bool     drbg_seeded;
    /* v7 monotonic state (per-process scope, per-thread under ZTS) */
    uint64_t      v7_key;       /* last (unix_ms << 12 | sub_ms), 60-bit time key */
    uint64_t      v7_randb;     /* 62-bit rand_b counter */
    zend_bool     v7_initialized;
    /* non-crypto fast PRNG (xoshiro256**) for uuid_v4_fast() */
    uint64_t      prng_s[4];
    zend_bool     prng_seeded;
ZEND_END_MODULE_GLOBALS(fast_uuid)

ZEND_EXTERN_MODULE_GLOBALS(fast_uuid)
#define FAST_UUID_G(v) ZEND_MODULE_GLOBALS_ACCESSOR(fast_uuid, v)

#if defined(ZTS) && defined(COMPILE_DL_FAST_UUID)
ZEND_TSRMLS_CACHE_EXTERN()
#endif

/* gen_stub (PHP master) emits 8.4+ constructs into the generated arginfo:
   zend_register_internal_class_with_flags(), the 6-argument ZEND_RAW_FENTRY
   (the __toString alias and every frameless function), and the frameless
   declarations plus zend_frameless_function_info tables. Polyfill them so the
   same fast_uuid_arginfo.h compiles on PHP 8.1-8.3. Must be defined before
   fast_uuid_arginfo.h is included.

   Pre-8.4 engines have no frameless calls, so the zflf_* handlers are compiled
   only on 8.4+ (FU_FLF_ONLY). Here ZEND_FRAMELESS_FUNCTION(name, arity);
   degrades to a file-scope struct forward declaration, handler slots become
   NULL, and ZEND_RAW_FENTRY drops the info table and doc comment, which the
   5-field zend_function_entry cannot hold. The then-unused static const tables
   live in a header, so -Wunused-const-variable stays silent. */
#if PHP_VERSION_ID < 80400
static zend_always_inline zend_class_entry *zend_register_internal_class_with_flags(
        zend_class_entry *class_entry, zend_class_entry *parent_ce, uint32_t ce_flags) {
    zend_class_entry *ce = zend_register_internal_class_ex(class_entry, parent_ce);
    if (ce && ce_flags) {
        ce->ce_flags |= ce_flags;
    }
    return ce;
}

typedef struct {
    void *handler;
    uint32_t num_args;
} zend_frameless_function_info;
# define ZEND_FRAMELESS_FUNCTION(name, arity) struct zflf_##name##_##arity
# define ZEND_FRAMELESS_FUNCTION_NAME(name, arity) NULL
# define FU_FLF_ONLY(...)

# undef ZEND_RAW_FENTRY
# define ZEND_RAW_FENTRY(zend_name, name, arg_info, flags, ...) \
    { zend_name, name, arg_info, \
      (uint32_t) (sizeof(arg_info)/sizeof(struct _zend_internal_arg_info)-1), \
      flags },
#else
# define FU_FLF_ONLY(...) __VA_ARGS__
#endif

#endif /* PHP_FAST_UUID_H */
