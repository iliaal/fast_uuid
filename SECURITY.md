# Security Policy

## Supported versions

Security fixes target the latest released `0.x` / `1.x` tag. The extension is
tested against PHP 8.1, 8.2, 8.3, 8.4, 8.5, and 8.6; report issues against any of these.

## Reporting a vulnerability

Email **ilia@ilia.ws** with a description, a reproduction (a short PHP script
plus the PHP and extension versions and the platform), and the impact you
observed. Please do not open a public issue for a suspected vulnerability before
a fix is available.

Expect an initial acknowledgement within a few days. Once confirmed, a patched
release is tagged and the advisory is published with credit unless you ask
otherwise.

## Scope notes

- `uuid4()` and the object/procedural default generators draw from a per-thread
  buffer. With hardware AES (x86/x86-64 AES-NI, ARMv8 Crypto Extensions) an
  AES-256-CTR generator refills it, replacing its key after every 8 KiB (fast key
  erasure) and mixing in fresh platform CSPRNG output every 64 KiB; its
  implementation must pass a FIPS-197 known-answer test at module startup or it
  is never used. Without hardware AES, the platform CSPRNG (`getrandom()` on
  Linux) refills the buffer directly. `uuid_v4_fast()` uses a
  non-cryptographic xoshiro256** PRNG and is documented as unsuitable for
  security-sensitive identifiers; misuse of `uuid_v4_fast()` is not a
  vulnerability.
- Fast key erasure means the generator's key cannot regenerate output from
  earlier 8 KiB refills. The buffer is not zeroed as bytes are served, so
  reading the generator's state (key, counter, buffer) recovers the already-served
  part of the current refill (up to 8 KiB), the unserved rest of the buffer, and,
  with hardware AES, all output until the next 64 KiB reseed. The platform-CSPRNG
  refill path keeps the same buffer and the same up-to-8 KiB exposure.
- Clones restored from one VM snapshot share the generator state. With hardware
  AES, each thread can repeat up to 64 KiB of output (about 4,096 v4 UUIDs)
  across clones, up from 8 KiB with the platform-CSPRNG refill. The next reseed
  separates the clones only if the guest kernel reseeds its own CSPRNG on restore
  (Linux does on hypervisors that expose a VM generation ID). Reseeding more
  often narrows the window; `FU_DRBG_RESEED_BYTES` in `fast_uuid.c` sets it.
- Parsing untrusted UUID strings is a supported use case. Memory-safety issues
  in any parse, format, or generation path are in scope and are tested under an
  ASan/UBSan-instrumented build.
