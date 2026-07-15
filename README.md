# fast_uuid

[![Tests](https://github.com/iliaal/fast_uuid/actions/workflows/tests.yml/badge.svg)](https://github.com/iliaal/fast_uuid/actions/workflows/tests.yml)
[![Windows Build](https://github.com/iliaal/fast_uuid/actions/workflows/release-windows.yml/badge.svg)](https://github.com/iliaal/fast_uuid/actions/workflows/release-windows.yml)
[![Version](https://img.shields.io/github/v/release/iliaal/fast_uuid)](https://github.com/iliaal/fast_uuid/releases)
[![License: BSD-3-Clause](https://img.shields.io/badge/License-BSD--3--Clause-green.svg)](https://opensource.org/licenses/BSD-3-Clause)
[![Follow @iliaa](https://img.shields.io/badge/Follow-@iliaa-000000?style=flat&logo=x&logoColor=white)](https://x.com/intent/follow?screen_name=iliaa)

![fast_uuid: RFC 9562 UUIDs for PHP in pure C, 11x to 57x faster than ramsey/uuid](images/fast_uuid-hero.jpg)

A high-performance PHP C extension for RFC 9562 / RFC 4122 UUID generation, **11x to 57x faster than `ramsey/uuid`** on v1/v4/v7 generation and 7x to 11x faster on parsing. It produces versions 1, 2 (DCE Security), 3, 4, 5, 6, 7, 8, plus nil and max. The engine is pure C (no C++/libstdc++). The object API mirrors `ramsey/uuid` under the `FastUuid` namespace, and procedural functions give a zero-allocation fast path for the hottest call sites.

Full API reference with runnable examples: [docs/index.html](docs/index.html). Benchmarks: [BENCHMARKS.md](BENCHMARKS.md).

## ⚡ Why it's fast

- **Batched CSPRNG**: `getrandom()` is amortized across ~500 v4s via an 8 KB per-thread buffer instead of one syscall per UUID. ramsey's per-call `random_bytes()` is the usual bottleneck.
- **No property table**: the object is 16 inline bytes plus a lazily-cached canonical string. No `HashTable`, no declared properties, custom create/free/clone/compare/cast handlers.
- **SIMD hex formatter**: x86-64 uses a runtime-dispatched SSSE3 `pshufb`-LUT path, and ARM64 uses a NEON table-lookup path. Both turn 16 bytes into 32 hex in a handful of vector ops, with a scalar LUT fallback for other architectures.
- **Procedural path**: `uuid_v4()` and friends return a `zend_string` with no object allocation, for ORM inserts and cache keys.

## Requirements

- PHP 8.1 through 8.6, NTS or ZTS. PHP 8.1/8.2/8.3 build via small `#if PHP_VERSION_ID` polyfills.
- x86-64 and ARM64 get the SIMD formatter automatically; other architectures fall back to the scalar path. No build flags needed either way.
- No external libraries. v1 and v6 use an internal RFC-compliant generator with a random node (multicast bit set, per RFC 9562 §5.1). v3 and v5 use PHP's bundled MD5/SHA1.

## 📦 Install

The quickest path is [PIE](https://github.com/php/pie), which resolves a prebuilt
binary for your platform (Windows `x86`/`x64` `NTS`/`TS`, Linux glibc
`x86_64`/`arm64`, macOS `arm64`) and falls back to a source build otherwise:

```sh
pie install iliaal/fast_uuid
```

Then enable it with `extension=fast_uuid` in your `php.ini`.

## 🛠️ Build from source

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
fromHexadecimal(Stringable|string $hex)    // 32 hex chars; Stringable covers ramsey's Type\Hexadecimal
fromDateTime(DateTimeInterface $dt, int|string|null $node = null, ?int $clockSeq = null)
isValid(string $uuid): bool
```

Instance methods:

```
toString(): string        __toString(): string      getBytes(): string        getHex(): string
getUrn(): string          getVersion(): ?int        getVariant(): int         getInteger(): string
getDateTime(): DateTimeImmutable                     getFields(): array        equals(mixed): bool
compareTo(mixed): int     jsonSerialize(): string   getTimestampMillis(): int
toBytes(): string         toHexadecimal(): string   toUrn(): string           toInteger(): string
```

- `toBytes()` / `toHexadecimal()` / `toUrn()` / `toInteger()` are aliases of `getBytes()` / `getHex()` / `getUrn()` / `getInteger()`, matching the `get*`→`to*` naming of the newer `ramsey/identifier` library.
- `getTimestampMillis()` returns the embedded timestamp as unix milliseconds for RFC time-based versions (v1, v2, v6, v7) and is much cheaper than `getDateTime()` since it builds no object; it throws `UnsupportedOperationException` for non-time-based versions and non-RFC variants.
- `Uuid::uuid7()` accepts a unix-millisecond `int` as well as a `DateTimeInterface`, which skips the DateTime machinery entirely. The procedural `uuid_v7_at(int $unixMillis)` is the fastest explicit-timestamp form.
- UUIDv7 carries sub-millisecond precision (RFC 9562 §6.2 Method 3): the sub-ms fraction is encoded in `rand_a` and a monotonic counter lives in `rand_b`, so v7s generated within the same millisecond still sort in time order (the tie-breaking counter is per process, or per thread under ZTS, so ~244 ns ties across threads or processes carry no order). `getDateTime()` reads back at millisecond precision, matching `ramsey/uuid`.
- `getVariant()` returns `0` (NCS), `2` (RFC 4122), `6` (Microsoft), `7` (future); `getVersion()` is `null` for nil/max and non-RFC variants.
- `getDateTime()` works for RFC time-based versions (v1, v2, v6, v7) and throws `FastUuid\Exception\UnsupportedOperationException` for non-time-based versions and non-RFC variants.
- `getFields()` returns an associative array of hex strings (`time_low`, `time_mid`, `time_hi_and_version`, `clock_seq_hi_and_reserved`, `clock_seq_low`, `node`). For the ramsey-shaped `FieldsInterface` / `Type` objects, use the compat layer below.
- `equals()` and `compareTo()` accept another UUID object (native, a compat wrapper, or any `Stringable` whose string form parses as a UUID) or its canonical string.
- `var_dump()` shows the value as a virtual `uuid` property, and `var_export()` output rebuilds through `Uuid::__set_state()`.

Constants: `NIL`, `MAX`, `NAMESPACE_DNS`, `NAMESPACE_URL`, `NAMESPACE_OID`, `NAMESPACE_X500`, `DCE_DOMAIN_PERSON`, `DCE_DOMAIN_GROUP`, `DCE_DOMAIN_ORG`.

Implements `FastUuid\UuidValueInterface`, which extends the lightweight
`FastUuid\UuidInterface` namespace marker with the complete native instance
contract. Factory return types use `UuidValueInterface`, while existing
userland `UuidInterface` implementations remain valid namespace arguments.

## DCE Security (v2)

```php
$u = \FastUuid\Uuid::uuid2(\FastUuid\Uuid::DCE_DOMAIN_PERSON);   // local id auto-fills from POSIX uid
$u->getVersion();        // 2
$u = \FastUuid\Uuid::uuid2(\FastUuid\Uuid::DCE_DOMAIN_GROUP, 4242);
```

The local identifier occupies bytes 0 to 3 (big-endian); the local domain is stored in byte 9. With domain PERSON or GROUP and a null identifier, the extension uses the process uid or gid; on Windows, where there is no POSIX uid/gid, an explicit `localIdentifier` is required.

## Exceptions

- `FastUuid\Exception\InvalidArgumentException` (extends `\InvalidArgumentException`): a bad length, node, or integer.
- `FastUuid\Exception\InvalidUuidStringException` (extends the above): an unparseable UUID string.
- `FastUuid\Exception\UnsupportedOperationException` (extends `\LogicException`, matching `ramsey/uuid` 4.x): raised by `getDateTime()` on a non-time-based version.

Out-of-range factory inputs are rejected, not silently truncated: a v7 timestamp past the 48-bit millisecond field, a `fromDateTime` instant outside the v1 Gregorian window, a non-canonical or >128-bit decimal string for `fromInteger`, a node outside `0..2^48-1`, a clock sequence outside `0..0x3fff`, or `uuid2` without an explicit local identifier for a non-PERSON/GROUP domain all throw `InvalidArgumentException`.

## Procedural API

```
uuid_v1() uuid_v3($ns, $name) uuid_v4() uuid_v4_fast() uuid_v5($ns, $name) uuid_v6() uuid_v7() uuid_v8($bytes)
uuid_v7_at($unixMillis)  // v7 from a unix-millisecond int (no DateTime)
uuid_v1_bin() uuid_v4_bin() uuid_v6_bin() uuid_v7_bin() uuid_v4_fast_bin()  // raw 16 bytes, no string
uuid_v3_bin($ns, $name) uuid_v5_bin($ns, $name) uuid_v8_bin($bytes) uuid_v7_at_bin($unixMillis)
uuid_v4_batch($n) uuid_v7_batch($n)          // array of $n canonical strings
uuid_v4_bin_batch($n) uuid_v7_bin_batch($n)  // array of $n raw 16-byte values
uuid_to_bin($uuid)   // canonical/parsed string -> 16 raw bytes
uuid_from_bin($bytes)// 16 raw bytes -> canonical string
uuid_is_valid($uuid) // bool
fast_uuid_random_bytes($length) // batched CSPRNG bytes, $length > 0
```

`uuid_v4_fast()` uses a non-cryptographic xoshiro256** PRNG. Use it only for non-security IDs.
Batch count is capped at 100,000 UUIDs per call, and `fast_uuid_random_bytes()` is capped at 16 MiB per call.

## ramsey/uuid compatibility layer (`FastUuid\Compat`)

`compat/` is a PSR-4 (`FastUuid\Compat\`) companion package (`iliaal/fast-uuid-compat`) that provides the cold-path ramsey ergonomics on top of the C engine. It ships in this repo's `compat/` directory and is not on Packagist yet; install it as a Composer [path repository](https://getcomposer.org/doc/05-repositories.md#path) (`composer config repositories.fast-uuid-compat path /path/to/fast_uuid/compat && composer require iliaal/fast-uuid-compat:@dev`) or autoload `FastUuid\Compat\` to `compat/src/`. It provides: `UuidFactory` / `UuidFactoryInterface`, the per-version `Rfc4122\UuidV1`…`UuidV8` / `NilUuid` / `MaxUuid` / `Nonstandard\Uuid` classes, `Rfc4122\UuidV2` with `getLocalDomain()` / `getLocalIdentifier()` / `getLocalDomainName()`, `Rfc4122\UuidV6` with `fromUuidV1()` / `toUuidV1()`, `Rfc4122\Fields` and `Nonstandard\Fields` (`FieldsInterface`), `Type\Hexadecimal` / `Type\Integer` with their `TypeInterface` / `NumberInterface` contracts, the codecs (`StringCodec`, `OrderedTimeCodec`, `TimestampFirstCombCodec`, `TimestampLastCombCodec`, `GuidStringCodec`), `Guid\Guid`, the providers (`RandomGeneratorInterface`, `NodeProviderInterface`, `TimeGeneratorInterface` + defaults), and the validators (`GenericValidator`, `NonstandardValidator`). Compat UUIDs implement both modern magic serialization and Ramsey's legacy `Serializable` methods. Node arguments accept `Type\Hexadecimal`, and UUIDv2 local identifiers accept `Type\Integer`.

Generation stays on the pure-C fast path; supplying a custom `RandomGeneratorInterface` / `TimeGeneratorInterface` / `NodeProviderInterface` intentionally routes off it where needed (ramsey behaviour) so application-supplied generators win. Custom node providers feed `uuid1()`, `uuid2()`, `uuid6()`, and `fromDateTime()` when no explicit node is passed. The compat `Uuid::uuid7()` facade also accepts a unix-millisecond `int`, matching the core object API. Decoded UUID objects retain the factory's active codec for strings, bytes, and serialization. `GuidStringCodec` swaps the first three fields in text but keeps `encodeBinary()` and `decodeBytes()` in RFC network order, matching ramsey/uuid; `Guid\Guid::getBytes()` exposes Microsoft's mixed-endian byte order. `OrderedTimeCodec` and the COMB codecs change their documented binary storage order. Migration from `ramsey/uuid` is largely a `use` swap from `Ramsey\Uuid\Uuid` to `FastUuid\Compat\Uuid`. The compat package has no external dependencies beyond the extension itself.

## 📊 Benchmarks

Throughput against `ramsey/uuid` 4.9.2 and the PECL `uuid` extension 1.3.0
(libuuid-backed). PHP 8.4.22 NTS, non-debug, no sanitizers; SSSE3 hex formatter
active (x86-64). Each operation runs 300,000 iterations after a 20,000-iteration
warmup; reported figure is the best of 40 runs. Million ops/sec, higher is
better:

| Operation        | fast_uuid (obj) | fast_uuid (proc) | ramsey/uuid | PECL uuid |
|------------------|----------------:|-----------------:|------------:|----------:|
| v4 gen→string    | 12.6            | **19.5**         | 1.10        | 0.47      |
| v1 gen→string    | 12.3            | **16.5**         | 0.29        | 8.22      |
| v7 gen→string    | 12.1            | **19.8**         | 0.66        | n/a       |
| parse→16 bytes   | 23              | **36**           | 3.18        | 5.28      |

Speedup over `ramsey/uuid`: v4 11.5x to 17.7x, v1 42x to 57x, v7 18.3x to 30x,
parse 7.2x to 11.3x.

Generating many at once amortizes the per-call overhead. The batch functions
return an array of 100 per call; procedural binary forms (`uuid_v4_bin()` etc.)
skip canonical formatting and return raw 16-byte strings. Million UUIDs/sec:

| Batch operation          | fast_uuid (proc) |
|--------------------------|-----------------:|
| `uuid_v4_batch`          | 22.5             |
| `uuid_v7_batch`          | 25               |
| `uuid_v4_bin_batch`      | 25               |
| `uuid_v7_bin_batch`      | **29**           |

The `fast_uuid` operations are fast enough (~50 ns) that scheduler noise
dominates a single run, so read the `fast_uuid` columns as order-of-magnitude,
not three-significant-digit (roughly ±10% run-to-run). `ramsey/uuid` (~900 ns)
and PECL (~2 µs) reproduce to within ~3%. Full table, the ARM64/NEON numbers,
the timestamp/DateTime API breakdown, and how to reproduce are in
[BENCHMARKS.md](BENCHMARKS.md).

## Testing

```sh
make test                                  # run-tests.php against the built .so
```

The suite (`tests/*.phpt`) covers every version, all parse forms, per-version `getDateTime`, fields/integer, node/clockSeq, the exception hierarchy, the procedural functions, the SIMD formatter, and the full compat layer. Verified green on PHP 8.1 / 8.2 / 8.3 / 8.4 / 8.4-ZTS / 8.5 / 8.6 (0 compiler warnings) and clean under an ASan/UBSan-instrumented build.

## Contributing

Build instructions, the stub-to-arginfo workflow, and the test conventions are in [CONTRIBUTING.md](CONTRIBUTING.md). Run the suite against more than one PHP version when you touch C, and add an ASan/UBSan run for any change to a parse, format, or generation path.

## Security

Report a vulnerability by email to ilia@ilia.ws. Details and scope are in [SECURITY.md](SECURITY.md).

## 🔗 Native PHP extensions

Companion native PHP extensions:

- **[php_excel](https://github.com/iliaal/php_excel)**: native Excel I/O via LibXL. 7-10× faster than PhpSpreadsheet, full XLS/XLSX with formulas, formatting, and styling.
- **[mdparser](https://github.com/iliaal/mdparser)**: native CommonMark + GFM markdown parser via md4c. 15-30× faster than pure-PHP libraries.
- **[php_clickhouse](https://github.com/iliaal/php_clickhouse)**: native ClickHouse client speaking the wire protocol directly. Picks up where SeasClick left off.
- **[pdo_duckdb](https://github.com/iliaal/pdo_duckdb)**: PDO driver for DuckDB, analytical SQL in your PHP stack.
- **[fastjson](https://github.com/iliaal/fastjson)**: drop-in faster `ext/json`, backed by yyjson. 6× encode, 2.7× decode, 5× validate.
- **[phpser](https://github.com/iliaal/phpser)**: decoder-optimized binary serializer for cache workloads. Faster than igbinary on packed numerics and DTO batches.
- **[fastchart](https://github.com/iliaal/fastchart)**: native chart-rendering extension. 38 chart types behind one fluent OO API, SVG-canonical with PNG/JPG/WebP and optional PDF output.
- **[statgrab](https://github.com/iliaal/statgrab)**: system statistics (CPU, memory, disk, network) via libstatgrab, no parsing /proc by hand.
- **[phonetic](https://github.com/iliaal/phonetic)**: native phonetic name matching (Double Metaphone, Beider-Morse, Daitch-Mokotoff, NYSIIS, Match Rating), the encoders PHP core lacks.

## License

BSD-3-Clause. See [LICENSE](LICENSE).

---

[Follow @iliaa on X](https://x.com/iliaa) • [Blog](https://ilia.ws) • If this sped up your UUID generation, ⭐ star it!
