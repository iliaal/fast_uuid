# fast_uuid

A high-performance PHP C extension for RFC 9562 UUID generation (v1, v3, v4, v5, v6, v7, v8, nil, max).
Pure C, no C++/libstdc++ in the engine. OO surface mirrors `ramsey/uuid` (under the `FastUuid` namespace),
plus procedural fast-path functions for the hottest code paths.

## Why it's fast

- **Batched CSPRNG** — `getrandom()` is amortized across ~500 v4s via an 8 KB per-thread buffer, instead of one syscall per UUID (ramsey's per-call `random_bytes()` is the usual bottleneck).
- **No property table** — the object is 16 inline bytes + a lazily-cached canonical string; no `HashTable`, no declared props, custom create/free/clone/compare/cast handlers.
- **LUT formatter** — byte→2-hex table, fully unrolled into the canonical 8-4-4-4-12 layout. (x86 SSE/AVX2 can slot into `fu_format36` later behind `__builtin_cpu_supports`; the scalar path is ARM64/Graviton-safe today.)
- **Procedural path** — `uuid_v4()` etc. return a `zend_string` with zero object allocation, for ORM inserts / cache keys.

## Build

```sh
phpize
./configure --enable-fast-uuid          # add --with-libuuid-dir=/usr if you want libuuid-backed v1
make
make test
php -d extension=$(pwd)/modules/fast_uuid.so -r 'echo \FastUuid\Uuid::uuid4(), "\n"; echo uuid_v7(), "\n";'
```

libuuid is **optional** — only used to back v1 (and v6, derived from v1). Without it, an internal RFC-compliant
v1 generator (random node + multicast bit) is used. v3/v5 use PHP's bundled MD5/SHA1; no libuuid needed.

The committed arginfo is hand-written inline for immediate build. To switch to the generated workflow,
run `php /path/to/php-src/build/gen_stub.php fast_uuid.stub.php` and include the produced header.

## OO API (`FastUuid\Uuid`)

Static: `uuid1 uuid3 uuid4 uuid5 uuid6 uuid7 uuid8 fromString fromBytes fromInteger isValid`
Instance: `toString __toString getBytes getHex getUrn getVersion getVariant getInteger getDateTime getFields equals compareTo jsonSerialize`
Constants: `NIL MAX NAMESPACE_DNS NAMESPACE_URL NAMESPACE_OID NAMESPACE_X500`
Implements: `FastUuid\UuidInterface`, `JsonSerializable`, `Stringable`

Migration from ramsey is a `use` swap: method names and return semantics match.

## Procedural API

`uuid_v1 uuid_v3 uuid_v4 uuid_v4_fast uuid_v5 uuid_v6 uuid_v7 uuid_v8`
`uuid_to_bin uuid_from_bin uuid_is_valid fast_uuid_random_bytes`

`uuid_v4_fast()` uses a non-crypto xoshiro256** PRNG — only for non-security IDs.

## Known divergences from ramsey (scaffold TODOs)

- `getFields()` returns a hex assoc array, not a `FieldsInterface` object.
- `getInteger()` returns a numeric string, not an `IntegerObject` wrapper.
- `uuid1()`/`uuid6()` ignore custom `$node`/`$clockSeq` overrides.
- `getDateTime()` is second-precision (drops sub-second).
- `uuid7($dateTime)` with an explicit time is non-monotonic.
- Parse errors throw `\InvalidArgumentException`, not ramsey's exception hierarchy.

## Roadmap

- Full `FieldsInterface` / `IntegerObject` fidelity.
- Optional `ramsey/uuid` `RandomGeneratorInterface` + `TimeGeneratorInterface` adapter package (`fast_uuid_random_bytes()` is the seam).
- x86 SIMD formatter behind CPU dispatch (function-level `target("avx2")`).
- Benchmarks vs `ramsey/uuid` and `ext-uuid`.

## License

TBD.
