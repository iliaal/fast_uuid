<?php

declare(strict_types=1);

namespace FastUuid\Compat;

use FastUuid\Compat\Rfc4122\Fields;
use FastUuid\Compat\Rfc4122\FieldsInterface;
use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Compat\Type\Integer as IntegerObject;

/**
 * Wraps a \FastUuid\Uuid (the C extension handle). Hot operations
 * (format/parse/compare/getBytes) delegate straight to C; the cold
 * ergonomics (Fields/Type objects) are assembled here in PHP.
 */
abstract class AbstractUuid implements UuidInterface
{
    public function __construct(protected \FastUuid\Uuid $core) {}

    public function getCore(): \FastUuid\Uuid { return $this->core; }

    // --- hot path: direct delegation -----------------------------------
    public function getBytes(): string { return $this->core->getBytes(); }
    public function toString(): string { return $this->core->toString(); }
    public function __toString(): string { return $this->core->toString(); }
    public function getUrn(): string { return $this->core->getUrn(); }
    public function getVersion(): ?int { return $this->core->getVersion(); }
    public function getVariant(): int { return $this->core->getVariant(); }
    public function jsonSerialize(): string { return $this->core->jsonSerialize(); }

    public function compareTo(mixed $other): int
    {
        if ($other instanceof UuidInterface) {
            return $this->core->compareTo($other->getCore());
        }
        return $this->core->compareTo($other);
    }

    public function equals(?object $other): bool
    {
        if ($other instanceof UuidInterface) {
            return $this->core->equals($other->getCore());
        }
        if ($other instanceof \FastUuid\Uuid) {
            return $this->core->equals($other);
        }
        return false;
    }

    // --- cold path: wrap into ramsey-shaped objects --------------------
    public function getHex(): Hexadecimal { return new Hexadecimal($this->core->getHex()); }
    public function getInteger(): IntegerObject { return new IntegerObject($this->core->getInteger()); }
    public function getFields(): FieldsInterface { return new Fields($this->core->getBytes()); }
    public function getDateTime(): \DateTimeInterface { return $this->core->getDateTime(); }

    // --- serialization parity ------------------------------------------
    public function serialize(): string { return $this->core->toString(); }

    public function unserialize(string $data): void
    {
        $core = \strlen($data) === 16
            ? \FastUuid\Uuid::fromBytes($data)
            : \FastUuid\Uuid::fromString($data);
        $this->restoreCore($core);
    }

    public function __serialize(): array { return ['bytes' => $this->core->getBytes()]; }
    public function __unserialize(array $data): void
    {
        if (!isset($data['bytes']) || !\is_string($data['bytes'])) {
            throw new \FastUuid\Exception\InvalidArgumentException('Malformed serialized UUID payload');
        }
        $this->restoreCore(\FastUuid\Uuid::fromBytes($data['bytes']));
    }

    private function restoreCore(\FastUuid\Uuid $core): void
    {
        // Reject a payload whose bytes don't match the concrete wrapper class
        // (e.g. a serialized UuidV4 tampered to carry v1 bytes).
        $expected = (new UuidFactory())->wrap($core);
        if (\get_class($expected) !== static::class) {
            throw new \FastUuid\Exception\InvalidArgumentException(\sprintf(
                'Serialized %s does not match its bytes (resolves to %s)',
                static::class,
                \get_class($expected),
            ));
        }
        $this->core = $core;
    }
}
