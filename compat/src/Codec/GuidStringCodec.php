<?php

declare(strict_types=1);

namespace FastUuid\Compat\Codec;

use FastUuid\Compat\UuidInterface;

/**
 * Mixed-endian (Microsoft GUID) codec: byte-reverses time_low, time_mid and
 * time_hi while leaving clock_seq and node intact. The swap is its own inverse.
 * Mirrors Ramsey\Uuid\Codec\GuidStringCodec.
 */
final class GuidStringCodec extends StringCodec
{
    private static function swap(string $b): string
    {
        return $b[3] . $b[2] . $b[1] . $b[0]
            . $b[5] . $b[4]
            . $b[7] . $b[6]
            . \substr($b, 8);
    }

    public function decodeBytes(string $bytes): UuidInterface
    {
        return parent::decodeBytes($bytes);
    }

    public function encode(UuidInterface $uuid): string
    {
        return self::bytesToString(self::swap($uuid->getCore()->getBytes()));
    }

    public function decode(string $encoded): UuidInterface
    {
        return $this->uuidFromBytes(self::swap(self::stringToBytes($encoded)));
    }
}
