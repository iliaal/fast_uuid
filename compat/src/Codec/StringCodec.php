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
        return self::bytesToString(WrapperClass::coreBytes($uuid));
    }

    public function encodeBinary(UuidInterface $uuid): string
    {
        return WrapperClass::coreBytes($uuid);
    }

    public function decode(string $encoded): UuidInterface
    {
        return WrapperClass::instantiateMapped(\FastUuid\Uuid::fromString($encoded), $this);
    }

    public function decodeBytes(string $bytes): UuidInterface
    {
        return WrapperClass::instantiateMapped(\FastUuid\Uuid::fromBytes($bytes), $this);
    }

    final protected function uuidFromBytes(string $bytes): UuidInterface
    {
        return WrapperClass::instantiateMapped(\FastUuid\Uuid::fromBytes($bytes), $this);
    }

    final protected function uuidFromString(string $uuid): UuidInterface
    {
        return WrapperClass::instantiateMapped(\FastUuid\Uuid::fromString($uuid), $this);
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
        if (\strlen($s) === 36) {
            if (!\preg_match('/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\z/', $s)) {
                throw new InvalidUuidStringException('Invalid UUID string');
            }
            $hex = \str_replace('-', '', $s);
        } elseif (\strlen($s) === 32) {
            if (!\preg_match('/\A[0-9a-fA-F]{32}\z/', $s)) {
                throw new InvalidUuidStringException('Invalid UUID string');
            }
            $hex = $s;
        } else {
            throw new InvalidUuidStringException('Invalid UUID string');
        }

        return (string) \hex2bin($hex);
    }
}
