<?php

declare(strict_types=1);

namespace FastUuid\Compat\Codec;

use FastUuid\Compat\UuidInterface;

/** Mirrors Ramsey\Uuid\Codec\CodecInterface. */
interface CodecInterface
{
    public function encode(UuidInterface $uuid): string;

    public function encodeBinary(UuidInterface $uuid): string;

    public function decode(string $encoded): UuidInterface;

    public function decodeBytes(string $bytes): UuidInterface;
}
