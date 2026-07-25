<?php

declare(strict_types=1);

namespace FastUuid\Compat;

use FastUuid\Compat\Codec\CodecInterface;
use FastUuid\Compat\Codec\StringCodec;
use FastUuid\Compat\Internal\ConstructionToken;
use FastUuid\Compat\Internal\WrapperClass;
use FastUuid\Compat\Nonstandard\Fields as NonstandardFields;
use FastUuid\Compat\Nonstandard\Uuid as NonstandardUuid;
use FastUuid\Compat\Rfc4122\Fields;
use FastUuid\Compat\Rfc4122\FieldsInterface;
use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Compat\Type\Integer as IntegerObject;

/**
 * Wraps a \FastUuid\Uuid (the C extension handle). Hot operations use the C
 * core directly for the default codec; custom representations route through
 * their codec. The cold ergonomics (Fields/Type objects) are assembled here.
 */
abstract class AbstractUuid implements UuidInterface
{
    protected \FastUuid\Uuid $core;
    protected ?CodecInterface $codec = null;
    private ?string $canonical = null;

    public function __construct(
        \FastUuid\Uuid $core,
        ?CodecInterface $codec = null,
        ?ConstructionToken $token = null,
    )
    {
        // Always validate version/class binding. ConstructionToken::Trusted is
        // retained for call-site compatibility but no longer skips the check
        // (it was a public capability token anyone could pass).
        unset($token);
        $this->assertCoreMatches($core);
        $this->core = $core;
        if ($codec !== null && \get_class($codec) !== StringCodec::class) {
            $this->codec = $codec;
        }
    }

    public function getCore(): \FastUuid\Uuid { return $this->core; }

    // --- hot path: direct delegation -----------------------------------
    public function getBytes(): string
    {
        return $this->codec === null
            ? $this->core->getBytes()
            : $this->codec->encodeBinary($this);
    }
    public function toString(): string
    {
        return $this->codec === null
            ? ($this->canonical ??= $this->core->toString())
            : $this->codec->encode($this);
    }
    public function __toString(): string { return $this->toString(); }
    // Identity forms are always RFC/network order from the core. Codec only
    // reshapes toString()/getBytes() presentation (Guid mixed-endian, COMB, …).
    public function getUrn(): string { return $this->core->getUrn(); }
    public function getVersion(): ?int { return $this->core->getVersion(); }
    public function getVariant(): int { return $this->core->getVariant(); }
    public function jsonSerialize(): string { return $this->toString(); }

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
    public function getInteger(): IntegerObject
    {
        return new IntegerObject($this->core->getInteger());
    }
    public function getFields(): FieldsInterface
    {
        return static::class === NonstandardUuid::class
            ? new NonstandardFields($this->core->getBytes())
            : new Fields($this->core->getBytes());
    }
    public function getDateTime(): \DateTimeInterface { return $this->core->getDateTime(); }

    // --- serialization parity ------------------------------------------
    // Always persist RFC network-order bytes so restore is independent of the
    // process-global factory codec (Guid/COMB presentation text is not portable).
    public function serialize(): string { return $this->core->getBytes(); }

    public function unserialize(string $data): void
    {
        if (\strlen($data) === 16) {
            $core = \FastUuid\Uuid::fromBytes($data);
            $this->assertCoreMatches($core);
            $this->core = $core;
            $this->codec = null;
            $this->canonical = null;
            return;
        }
        // Legacy text payloads (36-char canonical / urn / braced) from older
        // serialize() output: decode via the current factory.
        $uuid = Uuid::getFactory()->fromString($data);
        $this->restoreUuid($uuid);
    }

    public function __serialize(): array { return ['bytes' => $this->serialize()]; }
    public function __unserialize(array $data): void
    {
        if (!isset($data['bytes'])) {
            throw new \ValueError(\sprintf('%s(): Argument #1 ($data) is invalid', __METHOD__));
        }
        $this->unserialize($data['bytes']);
    }

    private function restoreUuid(UuidInterface $uuid): void
    {
        $core = $uuid->getCore();
        $this->assertCoreMatches($core);
        $this->core = $core;
        $this->codec = $uuid instanceof self ? $uuid->codec : null;
        $this->canonical = null;
    }

    private function assertCoreMatches(\FastUuid\Uuid $core): void
    {
        if (!WrapperClass::matches($core, static::class)) {
            $expected = WrapperClass::for($core);
            throw new \FastUuid\Exception\InvalidArgumentException(\sprintf(
                '%s cannot wrap bytes that resolve to %s',
                static::class,
                $expected,
            ));
        }
    }
}
