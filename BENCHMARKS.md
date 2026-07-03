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
| parse→16 bytes   | 23              | **36**           | 3.18        | 5.28      |

`v4 (non-crypto)` is `uuid_v4_fast()` (xoshiro256**), included for reference; it
is not for security-sensitive identifiers.

The parse path decodes hex through a 256-entry nibble table and an unrolled
16-byte loop instead of a per-character branch, which roughly doubled it over
the 0.2.x figures (16.2 → 36 proc).

## Speedup vs ramsey/uuid

| Operation | fast_uuid (obj) | fast_uuid (proc) |
|-----------|----------------:|-----------------:|
| v4        | 11.5x           | 17.7x            |
| v1        | 42x             | 57x              |
| v7        | 18.3x           | 30x              |
| parse     | 7.2x            | 11.3x            |

## Bulk generation

`uuid_v4_batch($n)` / `uuid_v7_batch($n)` build `$n` UUIDs in one call and return
them as an array, amortizing the per-call ZPP and return-value setup across the
batch. The `_bin_batch` forms return raw 16-byte strings instead of canonical
36-char strings and skip the hex formatter. Per-UUID throughput at a batch size
of 100, same machine and method as above (single-call `uuid_v4()`/`uuid_v7()`
shown for reference):

| Operation             | fast_uuid (proc) | vs single-call |
|-----------------------|-----------------:|---------------:|
| `uuid_v4()` (single)  | 19.5             | n/a            |
| `uuid_v4_batch`       | 22.5             | +15%           |
| `uuid_v4_bin_batch`   | 25               | +28%           |
| `uuid_v7()` (single)  | 19.8             | n/a            |
| `uuid_v7_batch`       | 25               | +26%           |
| `uuid_v7_bin_batch`   | **29**           | +47%           |

The single-call binary forms (`uuid_v4_bin()`, `uuid_v7_bin()`, and the
name-based `uuid_v3_bin()`/`uuid_v5_bin()`) return the same raw bytes without
formatting, but generation is CSPRNG-bound, so on a single call they run within
noise of their string counterparts; the win is in the batch and in avoiding a
format-then-reparse round trip when the caller wants bytes.

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

## ARM64 / NEON

These aarch64 figures predate the nibble-table parse rewrite, so the
`uuid_to_bin` / `parse→16 bytes` rows below still show the old scalar decoder
(~10.6). The optimization is SIMD-independent (a lookup table plus an unrolled
loop, no NEON), so it improves aarch64 in the same proportion as x86-64; these
numbers stay as originally measured rather than estimated.

On aarch64 a NEON table-lookup formatter (`vqtbl1q_u8`) replaces the scalar hex
path, mirroring the SSSE3 path on x86-64. Measured on a Neoverse-N1
(Graviton2-class, 4 cores), PHP 8.4.21 NTS release (`-O2`, no debug, no
sanitizers), one core pinned with `taskset`, best of 30 runs.

The formatter only touches UUID-to-string output, so the cleanest measure is
`uuid_from_bin()` (16 bytes to canonical string, no RNG). Building the same
source with NEON disabled (`make CC='gcc -DFU_DISABLE_NEON'`) isolates its
contribution:

| Path                          | NEON | scalar | NEON gain |
|-------------------------------|-----:|-------:|----------:|
| `uuid_from_bin` (pure format) | 18.0 | 15.5   | +16%      |
| `uuid_v7()` gen→string        | 9.4  | 8.2    | +15%      |
| `uuid_v4()` gen→string        | 10.2 | 9.1    | +12%      |
| `Uuid::uuid4()` obj→string    | 5.6  | 5.2    | +7%       |
| `uuid_to_bin` (parse)         | 10.6 | 10.7   | -1%       |

Parsing never calls the formatter, so its flat NEON-vs-scalar result is the
control: it confirms the gains above are the formatter and not some build
difference. The win is largest on pure formatting and shrinks as the RNG draw
and object allocation take a bigger share of each call.

Against ramsey/uuid 4.9.2 on the same core (NEON build):

| Operation      | fast_uuid (proc) | fast_uuid (obj) | ramsey/uuid | speedup (proc / obj) |
|----------------|-----------------:|----------------:|------------:|---------------------:|
| v4 gen→string  | 10.2             | 5.5             | 0.54        | 19x / 10x            |
| v7 gen→string  | 9.4              | n/a             | 0.32        | 29x                  |
| parse→16 bytes | 10.6             | n/a             | 1.67        | 6.3x                 |

Absolute throughput is roughly half the x86-64 figures above because the
Neoverse-N1 has a slower single thread, but the speedup over ramsey holds: its
pure-PHP work runs on the same slower core.

Reproduce: `bench/run_neon.sh <neon.so> <scalar.so>` drives the A/B and the
ramsey comparison.
