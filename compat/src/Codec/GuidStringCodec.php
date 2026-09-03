<?php

declare(strict_types=1);

namespace FastUuid\Compat\Codec;

use FastUuid\Compat\Internal\WrapperClass;
use FastUuid\Compat\UuidInterface;
use FastUuid\Exception\InvalidArgumentException;

/**
 * Mixed-endian (Microsoft GUID) codec: byte-reverses time_low, time_mid and
 * time_hi while leaving clock_seq and node intact. The swap is its own inverse.
 * Mirrors Ramsey\Uuid\Codec\GuidStringCodec.
 *
 * Only the byte array is mixed-endian. A GUID's *string* form is the same text
 * as the RFC one -- .NET's Guid.ToString() and Guid.ToByteArray() disagree on
 * purpose -- so encode()/decode() stay canonical and are inherited.
 */
final class GuidStringCodec extends StringCodec
{
    /** Mixed-endian field reorder; its own inverse. Shared with Guid::getBytes. */
    public static function swap(string $b): string
    {
        return $b[3] . $b[2] . $b[1] . $b[0]
            . $b[5] . $b[4]
            . $b[7] . $b[6]
            . \substr($b, 8);
    }

    public function encodeBinary(UuidInterface $uuid): string
    {
        return self::swap(WrapperClass::coreBytes($uuid));
    }

    public function decodeBytes(string $bytes): UuidInterface
    {
        if (\strlen($bytes) !== 16) {
            throw new InvalidArgumentException('Expected 16 bytes');
        }

        return $this->uuidFromBytes(self::swap($bytes));
    }
}
