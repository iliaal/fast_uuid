# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- Compat `Guid\Guid::unserialize()` restored through the process-global factory codec, so under a `GuidStringCodec` factory the network-order payload `serialize()` wrote was byte-swapped on restore, silently yielding a different identifier. Restore now parses natively and attaches the codec for presentation only.
- Compat `AbstractUuid::unserialize()` legacy-text payloads (pre-0.5.0 output) went through the factory codec as well: under a COMB codec the restored core identity was field-reordered while the presentation text masked it. Both payload shapes now parse natively; the presentation codec is still re-attached.
- Compat `UuidFactory::uuid2()` rejects a `$localDomain` outside 0..255 with `InvalidArgumentException` (`'localDomain must be 0..255 (PERSON=0, GROUP=1, ORG=2)'`), checked at the top of the method so both the default and custom-generator paths agree. Previously the custom path wrapped silently (`& 0xff`): domain 256 minted as 0, -1 as 255.
- Compat `uuid2()` with no `$localIdentifier` now fills from the process ids (`posix_getuid()` / `posix_getgid()`, `getmyuid()` / `getmygid()` fallback) and throws on Windows instead of a silent 0, matching the C core. Previously the compat factory used the script-owner ids while the core used the process ids, so the same call minted different DCE UUIDs on each layer.
- Compat `AbstractUuid::getInteger()` derives from the network-order core regardless of codec, so `fromInteger(getInteger())` round-trips under the COMB codecs. Previously it followed the presentation-permuted `getHex()`, silently returning a different UUID.
- Compat `UuidFactory::fromHexadecimal()` throws `InvalidUuidStringException` instead of base `InvalidArgumentException` on malformed input, agreeing with the core: exact-class catches now work on both paths, broader catches are unaffected.
- Compat `compareTo()` accepts objects only: scalars, garbage strings, and valid strings all throw `InvalidArgumentException` (never the `InvalidUuidString` child) instead of falling back to `strcmp()` over the codec-shaped text, where the same scalar threw or compared depending on global codec state.
- Compat `uuid7()` with a custom random generator lays out the millisecond timestamp plus 10 custom bytes directly, making no core monotonic call. Previously it minted from the monotonic generator (burning a slot) and then substituted uncorrelated bytes, so the emitted UUID could sort below an earlier same-tick UUID.

### Changed
- The tolerant parser and the compat validators accept the composed wrapper forms `{urn:uuid:...}` and `urn:uuid:{...}`; previously each wrapper worked alone but their composition was rejected.
- Compat `UuidInterface` no longer requires `getCore()`, so third-party Ramsey implementations and doubles can satisfy the interface. `UuidFactory` namespace arguments resolve through `getCore()` when present, falling back to `toString()` parsing with an `InvalidArgumentException` guard; the public `uuid3()` / `uuid5()` signatures are unchanged (`UuidInterface|string`). `getCore()` stays on `AbstractUuid` and `Guid\Guid`.
- Compat `equals()` widens to `mixed`: canonical and tolerant strings delegate to the core byte comparison, while scalars, null, and garbage strings return false instead of throwing `TypeError`.
- Compat validators now carry a real lax/strict distinction: `GenericValidator` enforces the version nibble (1-8) and the variant nibble (8/9/a/b), with the nil UUID explicitly exempt; `NonstandardValidator` stays shape-only with no nibble constraints. Both keep the `urn:uuid:` / `{...}` wrapper stripping, and `getPattern()` documents the inner canonical grammar (wrappers accepted by `validate()` are not in the pattern).
- CI pins PIE to 1.4.10 with a SHA256 check instead of executing the mutable `latest` URL, and every workflow job carries `timeout-minutes: 25` instead of the 6-hour runner ceiling.

## [0.6.0] - 2026-07-26

### Changed
- The compat layer now derives every identity form from `toString()`, as `ramsey/uuid` does: `getUrn()` is `'urn:uuid:' . toString()`, `getHex()` is `toString()` without the hyphens, `getInteger()` follows `getHex()`, and `equals()` / `compareTo()` compare `toString()`. Under the default codec `toString()` is the core's own canonical form, so nothing changes for callers who never set a codec. Under `TimestampFirstCombCodec` or `TimestampLastCombCodec`, which reshape the text, these forms now follow the COMB text instead of the network-order core.
- `GuidStringCodec` reshapes the byte array only. A GUID's string form is the same text as the RFC one (`.NET`'s `Guid.ToString()` and `Guid.ToByteArray()` disagree on purpose), so `encode()` / `decode()` are canonical and `encodeBinary()` / `decodeBytes()` carry the mixed-endian swap. `Guid\Guid::toString()` returns the canonical text and now agrees with its own `getUrn()`. `$factory->fromBytes()` under this codec reads GUID-ordered bytes, matching what `getBytes()` emits; pass canonical text to `fromString()` for network-order input.

  This is the one place the layer deliberately departs from `ramsey/uuid` 4.9.2, whose Guid feature set validates the version nibble at its post-swap position: over 400 `$guidFactory->uuid4()` calls, ramsey threw 190 times ("The byte string received does not contain a valid version") and emitted a wrong version nibble in the text 184 more, leaving 26 clean. The same loop here returns 400 usable v4 GUIDs.

## [0.5.0] - 2026-07-26

### Added
- `FastUuid\UuidValueInterface` carries the full value surface (`toString()`, `getBytes()`, `getFields()`, …); `FastUuid\UuidInterface` stays the bare marker. `FastUuid\Exception\UuidExceptionInterface` lets one `catch` cover every exception the extension throws.
- `FastUuid\Compat\UuidFactoryInterface`, and `UuidFactory` / `GenericValidator` are no longer `final`, so an application can subclass or replace them. `Uuid::setFactory()` accepts any `UuidFactoryInterface`; `uuid7()`, `uuid8()`, and `fromHexadecimal()` throw `UnsupportedOperationException` when the installed factory does not implement them (ramsey parity).
- Compat `Nonstandard\Fields`, the `Type\TypeInterface` / `Type\NumberInterface` value-object interfaces, and `\Serializable` on `UuidInterface` and `Rfc4122\FieldsInterface`, mirroring the ramsey shapes. `Guid\Guid` now implements `UuidInterface`.

### Changed
- `uuid_v7_batch()` and `uuid_v7_bin_batch()` read the clock once per call instead of once per UUID: about 2.6x faster per UUID (aarch64, release build). Every UUID in one call now carries the batch-start millisecond, so `getDateTime()` on the tail of a large batch can trail the wall clock by the batch duration (a 100,000 batch takes ~8 ms). Values stay unique, sorted, and monotonic against the next single call.
- `uuid_v4_batch()` and `uuid_v4_bin_batch()` draw CSPRNG bytes for 64 UUIDs per call instead of 16 bytes per UUID: 7.7% and 6.4% faster per UUID (aarch64, release build).
- The `node` and `localIdentifier` arguments are declared as unions in the parameter parser, so a wrong type now raises `TypeError` instead of `FastUuid\Exception\InvalidArgumentException`, and weak mode coerces `true` / `1.0` to an int before the range check.
- `uuid3()` and `uuid5()` reject a `$name` longer than 16 MiB.
- Compat `Rfc4122\Fields` validates the RFC variant and version in its constructor.
- Compat `serialize()` persists 16 raw network-order bytes instead of canonical text, so the payload no longer depends on the process-global factory codec. `unserialize()` still accepts the older text payloads.
- Compat `UuidFactory::fromHexadecimal()` and `fromInteger()` no longer route through the configured codec, reversing part of the 0.4.0 change: a hexadecimal or integer identity is always the network-order value, while the codec keeps reshaping `toString()` and `getBytes()`.
- A custom `RandomGeneratorInterface` now feeds compat `uuid7()`, and a custom `TimeGeneratorInterface` now feeds compat `uuid2()`.
- `Internal\ConstructionToken::Trusted` no longer skips the wrapper-class check, so every compat wrapper validates its core on construction. The check is one `getVersion()` call and a class-name comparison, but it runs on every wrapper, and construction is about a third slower than 0.4.0 (aarch64, release: `UuidFactory::fromBytes()` 1.81M to 1.21M ops/s).

### Fixed
- Compat `unserialize()` dropped the presentation codec, so `getBytes()` and `toString()` silently changed representation across a serialize round trip: a value written with `OrderedTimeCodec` read back in network byte order, which does not match the column it came from. The active factory codec is re-attached on restore.
- Procedural `uuid_v3()`, `uuid_v3_bin()`, `uuid_v5()`, and `uuid_v5_bin()` formatted an unwritten stack buffer when the name-length cap threw. The exception surfaced correctly, but the discarded return value was built from uninitialized bytes.
- A forked child wipes the inherited CSPRNG buffer and xoshiro seed rather than only marking them stale, so a memory disclosure in the child cannot recover the parent's remaining random bytes.
- Compat `Nonstandard\Uuid::getVersion()` returns null, agreeing with its `Fields`.
- Compat `Fields::getTimestamp()` no longer throws for versions that carry no timestamp; it returns the assembled field, as ramsey does.
- Compat `StringCodec` throws `InvalidUuidStringException` rather than `InvalidArgumentException` for a malformed UUID string.

## [0.4.0] - 2026-07-09

### Added
- Compat `Rfc4122\UuidV6::fromUuidV1()` / `toUuidV1()` and `Rfc4122\UuidV2::getLocalDomainName()`, plus the ramsey variant/version constants (`RFC_4122`, `UUID_TYPE_*`, `DCE_DOMAIN_NAMES`) on the `FastUuid\Compat\Uuid` facade.
- Windows release binaries now run the PHPT suite before upload (`release-windows.yml`), and CI tests PHP 8.6. An `nm` guard fails the Linux build if libgcc's `__cpu_model` symbol is linked in.
- Compat `Uuid::uuid7()` accepts an `int` unix-millisecond timestamp as well as a `DateTimeInterface`, matching the core object API.
- Custom node providers now feed the compat `uuid1()`, `uuid2()`, `uuid6()`, and `fromDateTime()` factories when no explicit node is passed.

### Changed
- `FastUuid\Exception\UnsupportedOperationException` now extends `\LogicException` instead of `\RuntimeException`, matching `ramsey/uuid` 4.x. Code that catches `\RuntimeException` for this exception must catch `\LogicException` or the class itself instead.
- SSSE3 detection uses a direct CPUID probe instead of `__builtin_cpu_supports()`, removing the libgcc `__cpu_model` dependency that broke `-shared` links under some toolchains (e.g. `zig cc`).
- Compat `Type\Hexadecimal` accepts a case-insensitive `0x`/`0X` prefix and any `Stringable`; `Type\Integer` accepts a leading `+` and whole floats. Both gained `__serialize()`/`__unserialize()`.
- `getVariant()` now returns `int` instead of `?int` on both the core `FastUuid\Uuid` and the compat `UuidInterface`; it never returned null.
- `getDateTime()` and `getTimestampMillis()` now throw `UnsupportedOperationException` for non-time-based versions and non-RFC variants instead of decoding a meaningless timestamp; the compat `Fields::getTimestamp()` mirrors this.
- Compat `UuidInterface::compareTo()` accepts `mixed` (a UUID object, canonical string, or `Stringable`), not only another `UuidInterface`.
- Compat factory decode methods honor the active codec: `fromString()`, `fromBytes()`, `fromHexadecimal()`, and `fromInteger()` route through the configured codec, so `GuidStringCodec` reads mixed-endian input.
- `uuid_to_bin()` throws `InvalidUuidStringException` instead of `InvalidArgumentException` on an unparseable UUID, matching `Uuid::fromString()`.
- `Uuid::fromString()` no longer echoes the rejected input in its exception message.

### Fixed
- Compat `UuidFactory::fromHexadecimal()` accepted hyphenated, URN, and braced strings (a regression from routing through the string codec). It now requires exactly 32 hex characters, matching the core `fromHexadecimal()` and ramsey.
- `getDateTime()` / `uuid7(DateTimeInterface)` / `fromDateTime()` on a `DateTime` subclass that overrides `format('u')` no longer silently shift the encoded timestamp: the microsecond field must be exactly six digits, so out-of-range, negative, or non-numeric values are rejected instead of coerced.
- The compat `GenericValidator` / `NonstandardValidator` reject over-long input before copying it, avoiding an out-of-memory fatal on a multi-megabyte `urn:uuid:` string.
- Live-clock generation (`uuid1`/`uuid6`/`uuid7` and `uuid2` with no explicit time) throws instead of emitting a wrapped timestamp when `clock_gettime()` fails, the system clock predates 1970, or the nanosecond counter would overflow (~year 2554).
- `pthread_atfork()` registration failure no longer latches the "registered" flag, so a later module init can retry the fork-safety setup.
- `Uuid::fromInteger()` rejects non-canonical (leading-zero) and out-of-range (>128-bit) decimal strings instead of silently wrapping.
- `uuid2()` rejects a non-canonical or out-of-range string `localIdentifier` instead of truncating it.
- Batch generators are capped at 100,000 UUIDs per call and `fast_uuid_random_bytes()` at 16 MiB, so an oversized request throws instead of exhausting memory.
- Compat `StringCodec` rejects UUID strings with misplaced hyphens (e.g. `0011223344-5546778899-aabbccddeeff`) that the previous `str_replace`-based decoder accepted.
- Compat `GenericValidator` and `NonstandardValidator` strip only a leading `urn:uuid:` prefix and matching braces, rejecting malformed wrappers like `urn:<uuid>` and `{urn:uuid:<uuid>}`.

## [0.3.0] - 2026-07-03

### Added
- Procedural binary generators that return the raw 16-byte value with no canonical formatting: `uuid_v1_bin()`, `uuid_v3_bin()`, `uuid_v4_bin()`, `uuid_v4_fast_bin()`, `uuid_v5_bin()`, `uuid_v6_bin()`, `uuid_v7_bin()`, `uuid_v7_at_bin()`, and `uuid_v8_bin()`. Store UUIDs in a `BINARY(16)` column or write them to a wire format without a string round-trip.
- Bulk generators that build many UUIDs in one call: `uuid_v4_batch($n)` and `uuid_v7_batch($n)` return an array of canonical strings; `uuid_v4_bin_batch($n)` and `uuid_v7_bin_batch($n)` return an array of raw 16-byte strings. They amortize the per-call overhead across the batch (roughly +15% to +47% per UUID at a batch of 100), and v7 batches stay monotonic within the call.
- `var_dump()` on a `FastUuid\Uuid` now shows the value as a virtual `uuid` property, and `var_export()` emits `FastUuid\Uuid::__set_state(['uuid' => ...])` that rebuilds the object (previously the export was empty and fatal to re-evaluate).
- Test coverage for previously untested surfaces: the v8 success path (core, procedural, compat mapping), procedural `uuid_v3()`/`uuid_v5()`, `getVariant()` Microsoft/future branches, `fromInteger()` rejection (out-of-range and non-numeric), right-length/bad-content and embedded-NUL parser rejections, the default compat node/time providers, and ramsey-parity vectors for the compat codecs and `Fields`. The binary and batch generators and the compat validator fast paths add their own tests.

### Changed
- Canonical UUID parsing runs about twice as fast: a 256-entry nibble lookup table and an unrolled 16-byte decode replace the per-character branch. This speeds `uuid_to_bin()`, `Uuid::fromString()`, `Uuid::isValid()`, and the namespace argument of `uuid3()`/`uuid5()`.
- The parser now requires hyphens at the four canonical separator positions and rejects inputs that use other separators, such as `a1b2c3d4_e5f6_4718_893a_4b5c6d7e8f90`. The old decoder skipped those positions without checking, so it accepted such strings; the stricter form matches RFC 9562 and ramsey/uuid.
- The compat `GenericValidator` and `NonstandardValidator` validate through a length-and-position check plus the C `Uuid::isValid()` instead of a PCRE match, so `ramsey/uuid`-compatible validation is faster.

### Fixed
- `ramsey/uuid` compat layer: `Fields::getTimestamp()` truncated v1/v6 timestamps on 32-bit PHP (a 48-bit `hexdec()` became a float, then the bit-shifts dropped precision). It now rebuilds the value as a hex string, exact on 32- and 64-bit.
- Compat `TimestampFirstCombCodec` used a byte rotation instead of ramsey's first-6/last-6 swap, so COMBs written by ramsey/uuid decoded to a *different* UUID (and vice versa). The layout now matches ramsey; COMB columns interop correctly.
- Compat `Fields::getTimestamp()` had no v2 branch: a DCE UUID's 32-bit local identifier leaked into the low timestamp bits. It now zeroes them, matching ramsey and the C decoder.
- Compat `Fields::getVersion()` now returns `null` for non-RFC variants, mirroring the 0.2.2 C-layer fix that had not been applied to the compat side.
- Compat `Fields::getClockSeq()` returns `ffff` for the max UUID (ramsey parity; previously the variant mask clipped it to `3fff`).
- Compat `Fields::getTimestamp()` zero-pads the v7 value to 15 hex digits (60 bits), matching ramsey's string form.
- Compat `UuidV2::getLocalIdentifier()` returned a negative value on 32-bit PHP for identifiers >= 2^31 (`unpack('N')` signedness); now formatted through `%u`.
- Compat `OrderedTimeCodec::decodeBytes()` now throws `UnsupportedOperationException` when the restored bytes are not a version-1 UUID (ramsey parity; previously mis-ordered input silently produced a plausible wrong UUID).
- `equals()`/`compareTo()` on the C extension now accept any `Stringable` UUID object (including compat wrappers), and compat `equals()` accepts a raw `\FastUuid\Uuid`; previously the package's own two object layers reported `false`/"Not comparable" for identical bytes.
- Compat `UuidFactory`: a custom `TimeGeneratorInterface` is now honored by `uuid6()` (v1 bytes reordered per RFC 9562), and the factory forces the version/variant nibbles on generator output (`uuid1()`/`uuid6()`), so generators ported from ramsey (whose contract leaves the nibbles to the factory) work unchanged.
- `uuid2()` with a null `localIdentifier` now throws on Windows instead of silently embedding identifier 0 (indistinguishable from uid 0); there is no POSIX uid/gid to fall back to there.
- Compat `Type\Hexadecimal`/`Type\Integer` validation regexes no longer accept a trailing newline (`/D` modifier; hardening beyond ramsey, which shares the unanchored pattern).
- `compat/composer.json` still required PHP >= 8.3; lowered to the project floor of 8.1.
- The ZTS `pthread_atfork` child handler now no-ops for a `fork()` issued from a native thread that never entered PHP, instead of resolving module globals off a missing TSRM cache.

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

[Unreleased]: https://github.com/iliaal/fast_uuid/compare/0.6.0...HEAD
[0.6.0]: https://github.com/iliaal/fast_uuid/compare/0.5.0...0.6.0
[0.5.0]: https://github.com/iliaal/fast_uuid/compare/0.4.0...0.5.0
[0.4.0]: https://github.com/iliaal/fast_uuid/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/iliaal/fast_uuid/compare/0.2.2...0.3.0
[0.2.2]: https://github.com/iliaal/fast_uuid/compare/0.2.1...0.2.2
[0.2.1]: https://github.com/iliaal/fast_uuid/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/iliaal/fast_uuid/compare/0.1.2...0.2.0
[0.1.2]: https://github.com/iliaal/fast_uuid/compare/0.1.1...0.1.2
[0.1.1]: https://github.com/iliaal/fast_uuid/compare/0.1.0...0.1.1
[0.1.0]: https://github.com/iliaal/fast_uuid/releases/tag/0.1.0
