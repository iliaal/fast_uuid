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
- Each operation runs 300,000 iterations after a 20,000-iteration warmup;
  reported figure is the best of 40 runs (ops/sec). A checksum accumulates each
  result so the work is not optimized away. The `fast_uuid` operations are fast
  enough (~50 ns) that scheduler noise dominates a single run; peak-of-40 is the
  least-interfered sample and still carries roughly ±10% run-to-run variance, so
  read the `fast_uuid` columns as order-of-magnitude, not three-significant-digit.
  ramsey/uuid (~900 ns) and PECL (~2 µs) reproduce to within ~3%.
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
| v4 gen→string    | 12.6            | **19.5**         | 1.10        | 0.47      |
| v4 (non-crypto)  | n/a             | **35.6**         | n/a         | n/a       |
| v1 gen→string    | 12.3            | **16.5**         | 0.29        | 8.22      |
| v7 gen→string    | 12.1            | **19.8**         | 0.66        | n/a       |
| parse→16 bytes   | 10.4            | **16.2**         | 3.18        | 5.28      |

`v4 (non-crypto)` is `uuid_v4_fast()` (xoshiro256**), included for reference; it
is not for security-sensitive identifiers.

## Speedup vs ramsey/uuid

| Operation | fast_uuid (obj) | fast_uuid (proc) |
|-----------|----------------:|-----------------:|
| v4        | 11.5x           | 17.7x            |
| v1        | 42x             | 57x              |
| v7        | 18.3x           | 30x              |
| parse     | 3.3x            | 5.1x             |

## Timestamp and DateTime APIs

Generating a v7 from an explicit time, and reading a UUID's timestamp back, used
to route through `call_user_function` (`getTimestamp()` + `format("u")`, or
`DateTimeImmutable::createFromFormat`). These now read and write ext/date's
internal `timelib_time` directly, which is roughly 3x faster, and a new
integer-millisecond API skips DateTime objects entirely. Same machine and method
as above; ramsey/uuid 4.9.2 for comparison.

| Operation                  | fast_uuid (obj) | fast_uuid (proc) | ramsey/uuid |
|----------------------------|----------------:|-----------------:|------------:|
| v7 from DateTime           | **11.9**        | n/a              | 0.68        |
| v7 from unix-ms int        | 13.4            | **21.2**         | n/a         |
| fromDateTime (v1)          | **12.5**        | n/a              | n/a         |
| getDateTime (read)         | **4.4**         | n/a              | 0.10        |
| getTimestampMillis (read)  | n/a             | **24.0**         | n/a         |

- `uuid7($dateTime)` runs ~17x faster than ramsey's; reading the embedded time
  with `getDateTime()` is ~44x faster.
- `uuid_v7_at($unixMillis)` and `Uuid::uuid7($int)` take a unix-millisecond
  integer and build v7 with no DateTime object, matching plain `uuid_v7()` speed.
  `getTimestampMillis()` reads the timestamp as an int without constructing a
  `DateTimeImmutable`, ~5x faster than `getDateTime()`. ramsey has no integer
  equivalent for either.

## Notes

- The batched CSPRNG is why v4 generation is an order of magnitude faster than
  ramsey: `getrandom()` is amortized across many UUIDs instead of one syscall
  each.
- Against PECL `uuid`, the procedural path is ~2x faster on v1 and ~41x faster
  on v4. PECL `uuid`'s v4 is much slower than its v1 because libuuid's random
  type draws fresh entropy per call rather than batching.
- ramsey v1 is its slowest path (clock-sequence and node bookkeeping in PHP).
  fast_uuid's v1 runs close to v4: the node and clock sequence come from the
  same batched CSPRNG, with no MAC lookup or clock-file coordination.
