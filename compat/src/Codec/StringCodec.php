<?php

declare(strict_types=1);

namespace FastUuid\Compat\Codec;

use FastUuid\Compat\Uuid;
use FastUuid\Compat\UuidInterface;
use FastUuid\Exception\InvalidArgumentException;

/** Canonical 8-4-4-4-12 string / raw-bytes codec. */
class StringCodec implements CodecInterface
{
    public function encode(UuidInterface $uuid): string
    {
        return $uuid->toString();
    }

    public function encodeBinary(UuidInterface $uuid): string
    {
        return $uuid->getBytes();
    }

    public function decode(string $encoded): UuidInterface
    {
        return Uuid::fromString($encoded);
    }

    public function decodeBytes(string $bytes): UuidInterface
    {
        return Uuid::fromBytes($bytes);
    }

    final protected static function bytesToString(string $b): string
    {
        if (\strlen($b) !== 16) {
            throw new InvalidArgumentException('Expected 16 bytes');
        }
        $h = \bin2hex($b);
        return \substr($h, 0, 8) . '-' . \substr($h, 8, 4) . '-' . \substr($h, 12, 4)
            . '-' . \substr($h, 16, 4) . '-' . \substr($h, 20, 12);
    }

    final protected static function stringToBytes(string $s): string
    {
        $hex = \str_replace('-', '', $s);
        if (\strlen($hex) !== 32 || !\ctype_xdigit($hex)) {
            throw new InvalidArgumentException('Invalid UUID string');
        }
        return (string) \hex2bin($hex);
    }
}
