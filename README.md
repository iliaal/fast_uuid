# fast_uuid

A high-performance PHP C extension for RFC 9562 / RFC 4122 UUID generation. It produces versions 1, 2 (DCE Security), 3, 4, 5, 6, 7, 8, plus nil and max. The engine is pure C (no C++/libstdc++). The object API mirrors `ramsey/uuid` under the `FastUuid` namespace, and procedural functions give a zero-allocation fast path for the hottest call sites.

Full API reference with runnable examples: [docs/index.html](docs/index.html). Benchmarks: [BENCHMARKS.md](BENCHMARKS.md).

## Why it's fast

- **Batched CSPRNG**: `getrandom()` is amortized across ~500 v4s via an 8 KB per-thread buffer instead of one syscall per UUID. ramsey's per-call `random_bytes()` is the usual bottleneck.
- **No property table**: the object is 16 inline bytes plus a lazily-cached canonical string. No `HashTable`, no declared properties, custom create/free/clone/compare/cast handlers.
- **SIMD hex formatter**: on x86-64 a runtime-dispatched SSSE3 `pshufb`-LUT path turns 16 bytes into 32 hex in a handful of vector ops. A scalar LUT path covers other architectures (ARM64/Graviton-safe). MINIT decides the dispatch once via `__builtin_cpu_supports("ssse3")`.
- **Procedural path**: `uuid_v4()` and friends return a `zend_string` with no object allocation, for ORM inserts and cache keys.

## Requirements

- PHP 8.3 through 8.6, NTS or ZTS. PHP 8.3 builds via two small `#if PHP_VERSION_ID < 80400` polyfills.
- x86-64 gets the SIMD formatter automatically; other architectures fall back to the scalar path. No build flags needed either way.
- No external libraries. v1 and v6 use an internal RFC-compliant generator with a random node (multicast bit set, per RFC 9562 §5.1). v3 and v5 use PHP's bundled MD5/SHA1.

## Install

The quickest path is [PIE](https://github.com/php/pie), which resolves a prebuilt
binary for your platform (Windows `x86`/`x64` `NTS`/`TS`, Linux glibc
`x86_64`/`arm64`, macOS `arm64`) and falls back to a source build otherwise:

```sh
pie install iliaal/fast_uuid
```

Then enable it with `extension=fast_uuid` in your `php.ini`.

## Build from source

```sh
phpize
./configure --enable-fast-uuid
make
make test
php -d extension="$(pwd)/modules/fast_uuid.so" -r 'echo \FastUuid\Uuid::uuid4(), "\n"; echo uuid_v7(), "\n";'
```

The arginfo header is generated from `fast_uuid.stub.php`. To regenerate after editing the stub:

```sh
php /path/to/php-src/build/gen_stub.php fast_uuid.stub.php
```

## Object API: `FastUuid\Uuid`

Static factories (all return `FastUuid\UuidInterface`):

```
uuid1(int|string|null $node = null, ?int $clockSeq = null)
uuid2(int $localDomain, int|string|null $localIdentifier = null, int|string|null $node = null, ?int $clockSeq = null)
uuid3(UuidInterface|string $ns, string $name)
uuid4()
uuid5(UuidInterface|string $ns, string $name)
uuid6(int|string|null $node = null, ?int $clockSeq = null)
uuid7(int|DateTimeInterface|null $dateTime = null)   // int = unix milliseconds
uuid8(string $bytes)                       // 16 raw bytes
fromString(string $uuid)                   // canonical, urn:uuid:, {braced}, bare 32-hex, any case
fromBytes(string $bytes)                   // 16 raw bytes
fromInteger(string $integer)               // decimal string
fromHexadecimal(string $hex)               // 32 hex chars
fromDateTime(DateTimeInterface $dt, int|string|null $node = null, ?int $clockSeq = null)
isValid(string $uuid): bool
```

Instance methods:

```
toString(): string        __toString(): string      getBytes(): string        getHex(): string
getUrn(): string          getVersion(): ?int        getVariant(): ?int        getInteger(): string
getDateTime(): DateTimeImmutable                     getFields(): array        equals(mixed): bool
compareTo(mixed): int     jsonSerialize(): string   getTimestampMillis(): int
toBytes(): string         toHexadecimal(): string   toUrn(): string           toInteger(): string
```

- `toBytes()` / `toHexadecimal()` / `toUrn()` / `toInteger()` are aliases of `getBytes()` / `getHex()` / `getUrn()` / `getInteger()`, matching the `get*`→`to*` naming of the newer `ramsey/identifier` library.
- `getTimestampMillis()` returns the embedded timestamp as unix milliseconds for the time-based versions (v1, v2, v6, v7) and is much cheaper than `getDateTime()` since it builds no object; it throws `UnsupportedOperationException` for v3/v4/v5/v8.
- `Uuid::uuid7()` accepts a unix-millisecond `int` as well as a `DateTimeInterface`, which skips the DateTime machinery entirely. The procedural `uuid_v7_at(int $unixMillis)` is the fastest explicit-timestamp form.
- UUIDv7 carries sub-millisecond precision (RFC 9562 §6.2 Method 3): the sub-ms fraction is encoded in `rand_a` and a monotonic counter lives in `rand_b`, so v7s generated within the same millisecond still sort in time order. `getDateTime()` reads back at millisecond precision, matching `ramsey/uuid`.
- `getVariant()` returns `0` (NCS), `2` (RFC 4122), `6` (Microsoft), `7` (future); `getVersion()` is `null` for nil/max.
- `getDateTime()` works for the time-based versions (v1, v2, v6, v7) and throws `FastUuid\Exception\UnsupportedOperationException` for v3/v4/v5/v8.
- `getFields()` returns an associative array of hex strings (`time_low`, `time_mid`, `time_hi_and_version`, `clock_seq_hi_and_reserved`, `clock_seq_low`, `node`). For the ramsey-shaped `FieldsInterface` / `Type` objects, use the compat layer below.
- `equals()` accepts another UUID object or its canonical string.

Constants: `NIL`, `MAX`, `NAMESPACE_DNS`, `NAMESPACE_URL`, `NAMESPACE_OID`, `NAMESPACE_X500`, `DCE_DOMAIN_PERSON`, `DCE_DOMAIN_GROUP`, `DCE_DOMAIN_ORG`.

Implements `FastUuid\UuidInterface`, `JsonSerializable`, `Stringable`.

## DCE Security (v2)

```php
$u = \FastUuid\Uuid::uuid2(\FastUuid\Uuid::DCE_DOMAIN_PERSON);   // local id auto-fills from POSIX uid
$u->getVersion();        // 2
$u = \FastUuid\Uuid::uuid2(\FastUuid\Uuid::DCE_DOMAIN_GROUP, 4242);
```

The local identifier occupies bytes 0 to 3 (big-endian); the local domain is stored in byte 9. With domain PERSON or GROUP and a null identifier, the extension uses the process uid or gid.

## Exceptions

- `FastUuid\Exception\InvalidArgumentException` (extends `\InvalidArgumentException`): a bad length, node, or integer.
- `FastUuid\Exception\InvalidUuidStringException` (extends the above): an unparseable UUID string.
- `FastUuid\Exception\UnsupportedOperationException` (extends `\RuntimeException`): raised by `getDateTime()` on a non-time-based version.

Out-of-range factory inputs are rejected, not silently truncated: a v7 timestamp past the 48-bit millisecond field, a `fromDateTime` instant outside the v1 Gregorian window, a node outside `0..2^48-1`, a clock sequence outside `0..0x3fff`, or `uuid2` without an explicit local identifier for a non-PERSON/GROUP domain all throw `InvalidArgumentException`.

## Procedural API

```
uuid_v1() uuid_v3($ns, $name) uuid_v4() uuid_v4_fast() uuid_v5($ns, $name) uuid_v6() uuid_v7() uuid_v8($bytes)
uuid_v7_at($unixMillis)  // v7 from a unix-millisecond int (no DateTime)
uuid_to_bin($uuid)   // canonical/parsed string -> 16 raw bytes
uuid_from_bin($bytes)// 16 raw bytes -> canonical string
uuid_is_valid($uuid) // bool
fast_uuid_random_bytes($length) // batched CSPRNG bytes, $length > 0
```

`uuid_v4_fast()` uses a non-cryptographic xoshiro256** PRNG. Use it only for non-security IDs.

## ramsey/uuid compatibility layer (`FastUuid\Compat`)

`compat/` is a PSR-4 (`FastUuid\Compat\`) companion package (`iliaal/fast-uuid-compat`) that provides the cold-path ramsey ergonomics on top of the C engine. It ships in this repo's `compat/` directory and is not on Packagist yet; install it as a Composer [path repository](https://getcomposer.org/doc/05-repositories.md#path) (`composer config repositories.fast-uuid-compat path /path/to/fast_uuid/compat && composer require iliaal/fast-uuid-compat:@dev`) or autoload `FastUuid\Compat\` to `compat/src/`. It provides: `UuidFactory`, the per-version `Rfc4122\UuidV1`…`UuidV8` / `NilUuid` / `MaxUuid` / `Nonstandard\Uuid` classes, `Rfc4122\UuidV2` with `getLocalDomain()` / `getLocalIdentifier()`, `Rfc4122\Fields` (`FieldsInterface`), `Type\Hexadecimal`, `Type\Integer`, the codecs (`StringCodec`, `OrderedTimeCodec`, `TimestampFirstCombCodec`, `TimestampLastCombCodec`, `GuidStringCodec`), `Guid\Guid`, the providers (`RandomGeneratorInterface`, `NodeProviderInterface`, `TimeGeneratorInterface` + defaults), and the validators (`GenericValidator`, `NonstandardValidator`).

Generation stays on the pure-C fast path; supplying a custom `RandomGeneratorInterface` / `TimeGeneratorInterface` / `NodeProviderInterface` intentionally routes off it (ramsey behaviour) so application-supplied generators win. Migration from `ramsey/uuid` is largely a `use` swap from `Ramsey\Uuid\Uuid` to `FastUuid\Compat\Uuid`. The compat package has no external dependencies beyond the extension itself.

## Benchmarks

On PHP 8.4 (NTS, non-debug, x86-64 with the SSSE3 formatter), generating a v4
UUID and its canonical string runs at ~19.5M ops/sec via the procedural path and
~12.6M via the object API, against ~1.1M for `ramsey/uuid` and ~0.47M for the PECL
`uuid` extension. fast_uuid runs 11x to 57x faster than ramsey on v1/v4/v7
generation, and 3x to 5x faster on parsing. Full table and method in
[BENCHMARKS.md](BENCHMARKS.md).

## Testing

```sh
make test                                  # run-tests.php against the built .so
```

The suite (`tests/*.phpt`) covers every version, all parse forms, per-version `getDateTime`, fields/integer, node/clockSeq, the exception hierarchy, the procedural functions, the SIMD formatter, and the full compat layer. Verified green on PHP 8.3 / 8.4 / 8.4-ZTS / 8.5 / 8.6 (0 compiler warnings) and clean under an ASan/UBSan-instrumented build.

## Contributing

Build instructions, the stub-to-arginfo workflow, and the test conventions are in [CONTRIBUTING.md](CONTRIBUTING.md). Run the suite against more than one PHP version when you touch C, and add an ASan/UBSan run for any change to a parse, format, or generation path.

## Security

Report a vulnerability by email to ilia@ilia.ws. Details and scope are in [SECURITY.md](SECURITY.md).

## License

BSD-3-Clause. See [LICENSE](LICENSE).
