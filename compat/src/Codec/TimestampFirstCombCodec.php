<?php

declare(strict_types=1);

namespace FastUuid\Compat\Codec;

use FastUuid\Compat\Uuid;
use FastUuid\Compat\UuidInterface;
use FastUuid\Exception\InvalidArgumentException;

/**
 * COMB codec that moves the trailing 48-bit timestamp to the front so the
 * stored/sorted value is time-ordered. Mirrors
 * Ramsey\Uuid\Codec\TimestampFirstCombCodec.
 */
final class TimestampFirstCombCodec extends StringCodec
{
    public function encodeBinary(UuidInterface $uuid): string
    {
        $b = $uuid->getBytes();

        return \substr($b, 10, 6) . \substr($b, 0, 10);
    }

    public function decodeBytes(string $bytes): UuidInterface
    {
        if (\strlen($bytes) !== 16) {
            throw new InvalidArgumentException('Expected 16 bytes');
        }

        return Uuid::fromBytes(\substr($bytes, 6, 10) . \substr($bytes, 0, 6));
    }

    public function encode(UuidInterface $uuid): string
    {
        return self::bytesToString($this->encodeBinary($uuid));
    }

    public function decode(string $encoded): UuidInterface
    {
        return $this->decodeBytes(self::stringToBytes($encoded));
    }
}
