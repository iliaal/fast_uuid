# Benchmarks

Throughput of `fast_uuid` against `ramsey/uuid` (4.9.2) and the PECL `uuid`
extension (1.3.0, libuuid-backed).

## Method

- Each engine runs in its own PHP process. `fast_uuid` and PECL `uuid` both
  define `uuid_is_valid()`, so they cannot be loaded together; separate
  processes also keep the engines isolated.
- PHP 8.4.22 NTS, non-debug, no sanitizers (a debug or ASan build inflates and
  reorders these numbers). The SSSE3 hex formatter is active (x86-64);
  `fast_uuid` has no external library dependency.
- Each operation runs 500,000 iterations after a 20,000-iteration warmup;
  reported figure is the best of 5 runs (ops/sec). A checksum accumulates each
  result so the work is not optimized away.
- "gen→string" measures generating one UUID and producing its canonical string,
  the common application need. "parse→16 bytes" measures parsing a canonical
  string to its 16 raw bytes. The procedural `fast_uuid` functions and PECL
  `uuid_create()` return strings directly (no object); the `fast_uuid` object
  API and `ramsey` build an object then format, so compare obj-to-ramsey and
  proc-to-ext-uuid for like-for-like.

Reproduce: build the extension against a release PHP, `composer require
ramsey/uuid`, `pecl install uuid`, then run a harness that times each operation
per engine in its own process.

## Results (million ops/sec, higher is better)

| Operation        | fast_uuid (obj) | fast_uuid (proc) | ramsey/uuid | PECL uuid |
|------------------|----------------:|-----------------:|------------:|----------:|
| v4 gen→string    | 13.0            | **21.0**         | 1.11        | 0.48      |
| v4 (non-crypto)  | n/a             | **37.2**         | n/a         | n/a       |
| v1 gen→string    | 10.9            | **15.6**         | 0.29        | 8.47      |
| v7 gen→string    | 13.5            | **19.3**         | 0.67        | n/a       |
| parse→16 bytes   | 11.6            | **18.4**         | 3.37        | 5.57      |

`v4 (non-crypto)` is `uuid_v4_fast()` (xoshiro256**), included for reference; it
is not for security-sensitive identifiers.

## Speedup vs ramsey/uuid

| Operation | fast_uuid (obj) | fast_uuid (proc) |
|-----------|----------------:|-----------------:|
| v4        | 11.7x           | 18.9x            |
| v1        | 37.6x           | 53.8x            |
| v7        | 20.1x           | 28.8x            |
| parse     | 3.4x            | 5.5x             |

## Notes

- The batched CSPRNG is why v4 generation is an order of magnitude faster than
  ramsey: `getrandom()` is amortized across many UUIDs instead of one syscall
  each.
- Against PECL `uuid`, the procedural path is 1.6x faster on v1 and 44x faster
  on v4. PECL `uuid`'s v4 is much slower than its v1 because libuuid's random
  type draws fresh entropy per call rather than batching.
- ramsey v1 is its slowest path (clock-sequence and node bookkeeping in PHP).
  fast_uuid's v1 runs close to v4: the node and clock sequence come from the
  same batched CSPRNG, with no MAC lookup or clock-file coordination.
