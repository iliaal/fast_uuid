<?php

declare(strict_types=1);

namespace FastUuid\Compat\Codec;

use FastUuid\Compat\Internal\WrapperClass;
use FastUuid\Compat\UuidInterface;
use FastUuid\Exception\InvalidArgumentException;
use FastUuid\Exception\UnsupportedOperationException;

/**
 * Binary ordering for version-1 UUIDs that sorts by time, for use as a MySQL
 * BINARY(16) primary key. Reorders the time fields to time_hi | time_mid |
 * time_low; the string form stays canonical. Mirrors
 * Ramsey\Uuid\Codec\OrderedTimeCodec.
 */
final class OrderedTimeCodec extends StringCodec
{
    public function encodeBinary(UuidInterface $uuid): string
    {
        if ($uuid->getVersion() !== 1) {
            throw new InvalidArgumentException('Expected a version 1 (time-based) UUID');
        }
        $b = WrapperClass::coreBytes($uuid);

        return $b[6] . $b[7] . $b[4] . $b[5] . $b[0] . $b[1] . $b[2] . $b[3] . \substr($b, 8);
    }

    public function decodeBytes(string $bytes): UuidInterface
    {
        if (\strlen($bytes) !== 16) {
            throw new InvalidArgumentException('Expected 16 bytes');
        }
        $b = $bytes;
        $restored = $b[4] . $b[5] . $b[6] . $b[7] . $b[2] . $b[3] . $b[0] . $b[1] . \substr($b, 8);

        $uuid = $this->uuidFromBytes($restored);
        if ($uuid->getVersion() !== 1) {
            throw new UnsupportedOperationException(
                'Attempting to decode a non-time-based UUID using OrderedTimeCodec'
            );
        }

        return $uuid;
    }
}
