<?php

declare(strict_types=1);

namespace FastUuid\Compat\Guid;

use FastUuid\Compat\Codec\GuidStringCodec;
use FastUuid\Compat\UuidInterface;

/**
 * A GUID is the same logical UUID presented in Microsoft's mixed-endian byte
 * order: getBytes()/toString() byte-reverse the first three fields. Mirrors
 * Ramsey\Uuid\Guid\Guid closely enough for storage interop.
 */
final class Guid implements \Stringable, \JsonSerializable
{
    private GuidStringCodec $codec;

    public function __construct(private readonly UuidInterface $uuid)
    {
        $this->codec = new GuidStringCodec();
    }

    public function getUuid(): UuidInterface
    {
        return $this->uuid;
    }

    /** Mixed-endian (GUID-ordered) raw bytes. */
    public function getBytes(): string
    {
        return \hex2bin(\str_replace('-', '', $this->codec->encode($this->uuid)));
    }

    public function toString(): string
    {
        return $this->codec->encode($this->uuid);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }
}
