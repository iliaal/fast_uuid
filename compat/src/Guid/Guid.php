<?php

declare(strict_types=1);

namespace FastUuid\Compat\Guid;

use FastUuid\Compat\Codec\GuidStringCodec;
use FastUuid\Compat\Internal\WrapperClass;
use FastUuid\Compat\Rfc4122\FieldsInterface;
use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Compat\Type\Integer as IntegerObject;
use FastUuid\Compat\Uuid;
use FastUuid\Compat\UuidInterface;

/**
 * A GUID is the same logical UUID presented in Microsoft's mixed-endian byte
 * order: getBytes()/toString() byte-reverse the first three fields. Identity
 * accessors (getHex/getInteger/getUrn/fields) stay network-order via the
 * inner UUID. Mirrors Ramsey\Uuid\Guid\Guid closely enough for storage interop.
 */
final class Guid implements UuidInterface
{
    private UuidInterface $uuid;

    public function __construct(UuidInterface $uuid)
    {
        $this->uuid = $uuid;
    }

    public function getUuid(): UuidInterface
    {
        return $this->uuid;
    }

    /**
     * Core handle for the inner UUID. Resolves via bytes so foreign
     * implementations without getCore() (CR-005) work as inners.
     */
    public function getCore(): \FastUuid\Uuid
    {
        return \FastUuid\Uuid::fromBytes(WrapperClass::coreBytes($this->uuid));
    }

    /** Mixed-endian (GUID-ordered) raw bytes. */
    public function getBytes(): string
    {
        return GuidStringCodec::swap(WrapperClass::coreBytes($this->uuid));
    }

    /** Canonical RFC text: a GUID's string form is not byte-swapped, only its
        byte array is. */
    public function toString(): string
    {
        return $this->uuid->toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    public function getUrn(): string
    {
        return $this->uuid->getUrn();
    }

    public function getHex(): Hexadecimal
    {
        return $this->uuid->getHex();
    }

    public function getInteger(): IntegerObject
    {
        return $this->uuid->getInteger();
    }

    public function getFields(): FieldsInterface
    {
        return $this->uuid->getFields();
    }

    public function getVersion(): ?int
    {
        return $this->uuid->getVersion();
    }

    public function getVariant(): int
    {
        return $this->uuid->getVariant();
    }

    public function getDateTime(): \DateTimeInterface
    {
        return $this->uuid->getDateTime();
    }

    public function equals(mixed $other): bool
    {
        if ($other instanceof self) {
            return $this->uuid->equals($other->uuid);
        }
        return $this->uuid->equals($other);
    }

    public function compareTo(mixed $other): int
    {
        if ($other instanceof self) {
            return $this->uuid->compareTo($other->uuid);
        }
        return $this->uuid->compareTo($other);
    }

    public function serialize(): string
    {
        return $this->uuid->serialize();
    }

    public function unserialize(string $data): void
    {
        // Parse natively and wrap for presentation only: routing through the
        // factory codec's decodeBytes() would byte-swap the network-order
        // payload serialize() wrote under a GuidStringCodec factory.
        $factory = Uuid::getFactory();
        $this->uuid = \strlen($data) === 16
            ? $factory->wrap(\FastUuid\Uuid::fromBytes($data))
            : $factory->wrap(\FastUuid\Uuid::fromString($data));
    }

    public function __serialize(): array
    {
        return ['bytes' => $this->serialize()];
    }

    public function __unserialize(array $data): void
    {
        if (!isset($data['bytes']) || !\is_string($data['bytes'])) {
            throw new \ValueError(\sprintf('%s(): Argument #1 ($data) is invalid', __METHOD__));
        }
        $this->unserialize($data['bytes']);
    }
}
