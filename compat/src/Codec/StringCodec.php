<?php

declare(strict_types=1);

namespace FastUuid\Compat\Codec;

use FastUuid\Compat\Internal\WrapperClass;
use FastUuid\Compat\UuidInterface;
use FastUuid\Exception\InvalidArgumentException;
use FastUuid\Exception\InvalidUuidStringException;

/** Canonical 8-4-4-4-12 string / raw-bytes codec. */
class StringCodec implements CodecInterface
{
    public function encode(UuidInterface $uuid): string
    {
        return WrapperClass::coreFrom($uuid)->toString();
    }

    public function encodeBinary(UuidInterface $uuid): string
    {
        return WrapperClass::coreBytes($uuid);
    }

    public function decode(string $encoded): UuidInterface
    {
        return \FastUuid\Compat\AbstractUuid::wrapCore(
            \FastUuid\Uuid::fromString($encoded),
            static::class === self::class ? null : $this,
        );
    }

    public function decodeBytes(string $bytes): UuidInterface
    {
        return \FastUuid\Compat\AbstractUuid::wrapCore(
            \FastUuid\Uuid::fromBytes($bytes),
            static::class === self::class ? null : $this,
        );
    }

    final protected function uuidFromBytes(string $bytes): UuidInterface
    {
        return \FastUuid\Compat\AbstractUuid::wrapCore(
            \FastUuid\Uuid::fromBytes($bytes),
            static::class === self::class ? null : $this,
        );
    }

    final protected function uuidFromString(string $uuid): UuidInterface
    {
        return \FastUuid\Compat\AbstractUuid::wrapCore(
            \FastUuid\Uuid::fromString($uuid),
            static::class === self::class ? null : $this,
        );
    }

    final protected static function bytesToString(string $b): string
    {
        if (\strlen($b) !== 16) {
            throw new InvalidArgumentException('Expected 16 bytes');
        }
        return \uuid_from_bin($b);
    }

    final protected static function stringToBytes(string $s): string
    {
        $len = \strlen($s);
        // Only canonical 36-char (hyphen at 8 rules out urn:/uuid:/{}) and bare 32-char hex.
        if (($len === 36 && $s[8] === '-') || $len === 32) {
            return \uuid_to_bin($s);
        }
        throw new InvalidUuidStringException('Invalid UUID string');
    }
}
