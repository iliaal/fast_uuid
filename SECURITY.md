# Security Policy

## Supported versions

Security fixes target the latest released `0.x` / `1.x` tag. The extension is
tested against PHP 8.3, 8.4, 8.5, and 8.6; report issues against any of these.

## Reporting a vulnerability

Email **ilia@ilia.ws** with a description, a reproduction (a short PHP script
plus the PHP and extension versions and the platform), and the impact you
observed. Please do not open a public issue for a suspected vulnerability before
a fix is available.

Expect an initial acknowledgement within a few days. Once confirmed, a patched
release is tagged and the advisory is published with credit unless you ask
otherwise.

## Scope notes

- `uuid4()` and the object/procedural default generators use the platform CSPRNG
  (`getrandom()`), batched through a per-thread buffer. `uuid_v4_fast()` uses a
  non-cryptographic xoshiro256** PRNG and is documented as unsuitable for
  security-sensitive identifiers; misuse of `uuid_v4_fast()` is not a
  vulnerability.
- Parsing untrusted UUID strings is a supported use case. Memory-safety issues
  in any parse, format, or generation path are in scope and are tested under an
  ASan/UBSan-instrumented build.
