# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- RFC 9562 UUID generation implemented in C: versions 1, 3, 4, 6, 7, 8, plus
  the nil and max UUIDs.
- `FastUuid\Uuid` (final) with ramsey/uuid-shaped static factories
  (`uuid1`/`uuid3`/`uuid4`/`uuid5`/`uuid6`/`uuid7`/`uuid8`, `fromString`,
  `fromBytes`, `fromInteger`, `fromHexadecimal`, `fromDateTime`, `isValid`) and
  instance methods (`toString`/`__toString`, `getBytes`, `getHex`, `getUrn`,
  `getVersion`, `getVariant`, `getInteger`, `getDateTime`, `getFields`,
  `equals`, `compareTo`, `jsonSerialize`).
- `FastUuid\UuidInterface`, the `FastUuid\Exception\*` hierarchy, and the
  `NIL`/`MAX`/`NAMESPACE_*` class constants.
- Procedural fast-path functions returning canonical strings without object
  allocation: `uuid_v1`, `uuid_v3`, `uuid_v4`, `uuid_v4_fast`, `uuid_v5`,
  `uuid_v6`, `uuid_v7`, `uuid_v8`, `uuid_to_bin`, `uuid_from_bin`,
  `uuid_is_valid`, `fast_uuid_random_bytes`.
- Batched CSPRNG (8 KB module-global buffer) amortizing `getrandom()` across
  many UUIDs; xoshiro256** non-cryptographic generator behind `uuid_v4_fast`.
- Optional libuuid backend for v1 (`uuid_generate_time_safe`) when present and
  no node/clock-sequence override is requested; internal generator otherwise.
- `FastUuid\Compat` companion package: ramsey/uuid-shaped object API
  (per-version classes, `Rfc4122\Fields`, `Type\Hexadecimal`/`Type\Integer`)
  layered over the C core.

### Notes
- Minimum supported PHP version is 8.3. The arginfo is generated from
  `fast_uuid.stub.php` via `gen_stub`, whose output uses the 8.3+ internal
  class-registration API.
