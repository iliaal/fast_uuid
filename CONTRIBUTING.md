# Contributing

## Layout

- `fast_uuid.c`: the C engine (generation, parsing, formatting, the object
  model, procedural functions).
- `fast_uuid.stub.php`: the source of truth for the public API. Arginfo is
  generated from it; do not hand-edit `fast_uuid_arginfo.h`.
- `php_fast_uuid.h`: module header, version, and the two
  `#if PHP_VERSION_ID < 80400` polyfills that keep PHP 8.3 building against
  `gen_stub` output.
- `compat/`: the `FastUuid\Compat` ramsey-shaped package (pure PHP, PSR-4).
- `tests/`: `.phpt` tests. `tests/_autoload.inc` is the compat-layer autoloader
  the compat tests require.

## Build and test

```sh
phpize
./configure --enable-fast-uuid
make
make test
```

After editing `fast_uuid.stub.php`, regenerate the arginfo header:

```sh
php /path/to/php-src/build/gen_stub.php fast_uuid.stub.php
```

## Writing tests

Generation is non-deterministic, so tests assert booleans rather than literal
UUID values: write `var_dump(<bool expr>);` and put `bool(true)` in `--EXPECT--`.
Each test declares `--EXTENSIONS--\nfast_uuid`. Compat tests start
their `--FILE--` body with `require __DIR__ . '/_autoload.inc';` as the first
statement after any `declare(strict_types=1)`, which must legally come first. The
layer has no external extension dependencies, so tests must not require `ctype`
or other extensions.

## Before opening a PR

- Build with zero compiler warnings.
- Run the suite against more than one PHP version when touching C. The intended
  support range is 8.1 through 8.6, NTS and ZTS.
- For any change to a parse, format, or generation path, run the suite once more
  against an ASan/UBSan-instrumented build (a `.so` built `-fsanitize=address,undefined`
  loaded by an ASan-instrumented PHP, with `USE_ZEND_ALLOC=0`). A stack overflow
  or wrong-buffer write can pass a normal build and a passing `.phpt`; only the
  instrumented run catches it.
- Update `CHANGELOG.md` under `[Unreleased]`.

## Commit and PR style

Keep commit subjects in the imperative mood and bodies to a terse paragraph
explaining the why. No AI-attribution trailers. Match the surrounding code's
style; default to no comments unless a non-obvious constraint needs one.
