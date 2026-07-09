<?php

declare(strict_types=1);

namespace FastUuid\Compat\Codec;

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
        return self::uuidFromString($encoded);
    }

    public function decodeBytes(string $bytes): UuidInterface
    {
        return self::uuidFromBytes($bytes);
    }

    private static ?\FastUuid\Compat\UuidFactory $wrapFactory = null;

    /** Shared default factory used only for version-class wrapping (no decode). */
    private static function wrapFactory(): \FastUuid\Compat\UuidFactory
    {
        return self::$wrapFactory ??= new \FastUuid\Compat\UuidFactory();
    }

    final protected static function uuidFromBytes(string $bytes): UuidInterface
    {
        return self::wrapFactory()->wrap(\FastUuid\Uuid::fromBytes($bytes));
    }

    final protected static function uuidFromString(string $uuid): UuidInterface
    {
        return self::wrapFactory()->wrap(\FastUuid\Uuid::fromString($uuid));
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
        if (\strlen($s) === 36) {
            if (!\preg_match('/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\z/', $s)) {
                throw new InvalidArgumentException('Invalid UUID string');
            }
            $hex = \str_replace('-', '', $s);
        } elseif (\strlen($s) === 32) {
            if (!\preg_match('/\A[0-9a-fA-F]{32}\z/', $s)) {
                throw new InvalidArgumentException('Invalid UUID string');
            }
            $hex = $s;
        } else {
            throw new InvalidArgumentException('Invalid UUID string');
        }

        return (string) \hex2bin($hex);
    }
}
