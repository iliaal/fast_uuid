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

#define PHP_FAST_UUID_VERSION "0.1.0"

#ifdef ZTS
#include "TSRM.h"
#endif

ZEND_BEGIN_MODULE_GLOBALS(fast_uuid)
    /* batched CSPRNG buffer: amortizes getrandom() across many UUIDs */
    unsigned char rbuf[8192];
    size_t        rpos;
    /* v7 monotonic state (per-process scope, per-thread under ZTS) */
    uint64_t      v7_ts;        /* last unix ms */
    uint64_t      v7_counter;   /* 42-bit counter */
    /* non-crypto fast PRNG (xoshiro256**) for uuid_v4_fast() */
    uint64_t      prng_s[4];
    zend_bool     prng_seeded;
ZEND_END_MODULE_GLOBALS(fast_uuid)

ZEND_EXTERN_MODULE_GLOBALS(fast_uuid)
#define FAST_UUID_G(v) ZEND_MODULE_GLOBALS_ACCESSOR(fast_uuid, v)

#if defined(ZTS) && defined(COMPILE_DL_FAST_UUID)
ZEND_TSRMLS_CACHE_EXTERN()
#endif

#endif /* PHP_FAST_UUID_H */
