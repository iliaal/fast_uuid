# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- RFC 9562 / RFC 4122 UUID generation implemented in C: versions 1, 2 (DCE
  Security), 3, 4, 5, 6, 7, 8, plus the nil and max UUIDs.
- `FastUuid\Uuid` (final) with ramsey/uuid-shaped static factories
  (`uuid1`/`uuid2`/`uuid3`/`uuid4`/`uuid5`/`uuid6`/`uuid7`/`uuid8`, `fromString`,
  `fromBytes`, `fromInteger`, `fromHexadecimal`, `fromDateTime`, `isValid`) and
  instance methods (`toString`/`__toString`, `getBytes`, `getHex`, `getUrn`,
  `getVersion`, `getVariant`, `getInteger`, `getDateTime`, `getFields`,
  `equals`, `compareTo`, `jsonSerialize`).
- `uuid2()` DCE Security UUIDs with the `DCE_DOMAIN_PERSON`/`GROUP`/`ORG`
  constants; the local identifier defaults to the POSIX uid/gid for the
  person/group domains. `getDateTime()` resolves the coarse v2 timestamp.
- `uuid1()` and `uuid6()` honor explicit node (12-hex string or integer) and
  clock-sequence overrides.
- `FastUuid\UuidInterface`, the `FastUuid\Exception\*` hierarchy
  (`InvalidArgumentException` extends `\InvalidArgumentException`,
  `InvalidUuidStringException` extends it, `UnsupportedOperationException`
  extends `\RuntimeException`), and the `NIL`/`MAX`/`NAMESPACE_*`/`DCE_DOMAIN_*`
  class constants.
- Procedural fast-path functions returning canonical strings without object
  allocation: `uuid_v1`, `uuid_v3`, `uuid_v4`, `uuid_v4_fast`, `uuid_v5`,
  `uuid_v6`, `uuid_v7`, `uuid_v8`, `uuid_to_bin`, `uuid_from_bin`,
  `uuid_is_valid`, `fast_uuid_random_bytes`.
- Batched CSPRNG (8 KB module-global buffer) amortizing `getrandom()` across
  many UUIDs; xoshiro256** non-cryptographic generator behind `uuid_v4_fast`.
- Runtime-dispatched x86-64 SSSE3 `pshufb`-LUT hex formatter, selected once in
  MINIT via `__builtin_cpu_supports`; scalar LUT fallback on other
  architectures.
- Optional libuuid backend for v1 (`uuid_generate_time_safe`) when present and
  no node/clock-sequence override is requested; internal generator otherwise.
- `FastUuid\Compat` companion package: ramsey/uuid-shaped object API layered
  over the C core. Per-version classes (`Rfc4122\UuidV1`..`UuidV8`, `NilUuid`,
  `MaxUuid`, `Nonstandard\Uuid`), `Rfc4122\UuidV2` with `getLocalDomain()` /
  `getLocalIdentifier()`, `Rfc4122\Fields` (`FieldsInterface`),
  `Type\Hexadecimal` / `Type\Integer`, the codecs (`StringCodec`,
  `OrderedTimeCodec`, `TimestampFirstCombCodec`, `TimestampLastCombCodec`,
  `GuidStringCodec`), `Guid\Guid`, the random/node/time providers, and the
  `GenericValidator` / `NonstandardValidator` validators. No external
  dependencies beyond the extension.

### Notes
- Minimum supported PHP version is 8.3 (verified on 8.3, 8.4, 8.4-ZTS, 8.5,
  8.6). The arginfo is generated from `fast_uuid.stub.php` via `gen_stub`, whose
  output uses the 8.4+ internal class-registration API; two
  `#if PHP_VERSION_ID < 80400` polyfills in `php_fast_uuid.h` keep 8.3 building.
