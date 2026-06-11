# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.2] - 2026-06-11

### Fixed

- `getVersion()` now returns `null` for UUIDs whose variant bits are not RFC 4122
  (`10x`), matching ramsey/uuid. Previously it read the version nibble
  unconditionally and returned a meaningless value for NCS/Microsoft/future-variant
  UUIDs.
- `getDateTime()` no longer overflows for far-future v7 timestamps that exceed
  `zend_long`'s range on 32-bit PHP; it falls back to string-based
  `DateTimeImmutable` construction in that case.
- `getTimestampMillis()` throws `FastUuid\Exception\UnsupportedOperationException`
  on 32-bit builds when the millisecond value can't be represented as a platform
  integer, instead of silently returning a truncated value.

## [0.2.1] - 2026-06-06

### Fixed

- Windows 32-bit (x86) builds for PHP 8.1 and 8.2 now link. The high-resolution
  wall clock (`GetSystemTimePreciseAsFileTime`) is resolved at runtime via
  `GetProcAddress` instead of import-linked, because the x86 import library in
  the 8.1/8.2 Windows build SDK omits the symbol (`LNK2019`). Pre-Win8 hosts
  fall back to `GetSystemTimeAsFileTime`. 0.2.0 attached no Windows DLLs for any
  version because the x86 8.1/8.2 link failure blocked the upload step.

## [0.2.0] - 2026-06-06

### Added

- PHP 8.1 and 8.2 support (lowered the minimum from 8.3).
- ARM64 builds now use a NEON table-lookup hex formatter for UUID string output,
  matching the existing SSSE3 fast path on x86-64.

### Fixed

- Direct `$uuid->__unserialize([0 => $bytes])` on an already-stringified object
  now invalidates the cached canonical string, so the string accessors
  (`toString`/`__toString`/`getHex`/`getUrn`/`jsonSerialize`) and the byte
  accessors agree after re-init. The normal `unserialize()` path was never
  affected.

## [0.1.2] - 2026-06-01

### Fixed

- Windows builds now compile under MSVC. The wall clock uses
  `GetSystemTimePreciseAsFileTime` (100ns resolution) instead of POSIX
  `clock_gettime`, and the cast handler returns `zend_result` to match its
  signature. 0.1.0 and 0.1.1 attached no Windows DLLs because the MSVC build
  failed; this release ships them.

## [0.1.1] - 2026-06-01

### Fixed

- Windows builds: added the `config.w32` that `php-windows-builder` needs, so
  the prebuilt Windows DLLs (8.3/8.4/8.5 x x86/x64 x NTS/TS) now attach to the
  release. 0.1.0 shipped Linux and macOS binaries only.

## [0.1.0] - 2026-06-01

### Added

- Initial release: RFC 9562 / RFC 4122 UUID generation as a pure-C PHP extension
  (no libuuid, no C++, no external dependencies).
- Generate every version: v1, v2 (DCE Security), v3, v4, v5, v6, v7, v8, plus the
  nil and max UUIDs.
- A `ramsey/uuid`-shaped object API (`FastUuid\Uuid`) and procedural fast-path
  functions (`uuid_v1()`, `uuid_v4()`, `uuid_v7()`, `uuid_to_bin()`, ...) that
  return a string with no object allocation.
- UUIDv7 carries sub-millisecond precision (RFC 9562 §6.2 Method 3: the sub-ms
  fraction is encoded in `rand_a`) plus a per-process monotonic counter, so v7s
  generated within the same millisecond still sort in time order.
- Integer-millisecond v7 API that skips DateTime entirely: `uuid_v7_at(int
  $unixMillis)`, `Uuid::uuid7(int)`, and `getTimestampMillis(): int`.
- `to*()` converter aliases (`toBytes`, `toHexadecimal`, `toUrn`, `toInteger`)
  matching the `get*`->`to*` naming of `ramsey/identifier`.
- A batched, fork-safe CSPRNG (amortizes `getrandom()` across many UUIDs), an
  SSSE3 hex formatter on x86-64, and a tolerant parser (canonical, `urn:uuid:`,
  braced, bare 32-hex, any case).
- Out-of-range factory inputs (timestamp, node, clock sequence, DCE domain) throw
  `FastUuid\Exception\InvalidArgumentException` instead of silently truncating.
- `FastUuid\Compat`, a pure-PHP companion that mirrors the `ramsey/uuid` API
  (`UuidFactory`, per-version `Rfc4122\*` classes, `Type\*`, codecs, providers,
  validators) on top of the C core.
- Prebuilt release binaries for Windows (8.3/8.4/8.5 x x86/x64 x NTS/TS) and
  Linux glibc x86_64/arm64 + macOS arm64 (8.4/8.5), with a PIE source-build
  fallback for other targets.

[Unreleased]: https://github.com/iliaal/fast_uuid/compare/0.2.2...HEAD
[0.2.2]: https://github.com/iliaal/fast_uuid/compare/0.2.1...0.2.2
[0.2.1]: https://github.com/iliaal/fast_uuid/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/iliaal/fast_uuid/compare/0.1.2...0.2.0
[0.1.2]: https://github.com/iliaal/fast_uuid/compare/0.1.1...0.1.2
[0.1.1]: https://github.com/iliaal/fast_uuid/compare/0.1.0...0.1.1
[0.1.0]: https://github.com/iliaal/fast_uuid/releases/tag/0.1.0
