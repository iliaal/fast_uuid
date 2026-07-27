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
    // Every derived form follows toString(), as ramsey defines them: getUrn() is
    // 'urn:uuid:' . toString(), getHex() is toString() without the hyphens, and
    // comparison is strcmp over toString(). Under the default codec toString()
    // is the core's own canonical form, so these read the core directly.
    public function getUrn(): string
    {
        return $this->codec === null
            ? $this->core->getUrn()
            : 'urn:uuid:' . $this->toString();
    }
    public function getVersion(): ?int { return $this->core->getVersion(); }
    public function getVariant(): int { return $this->core->getVariant(); }
    public function jsonSerialize(): string { return $this->toString(); }

    public function compareTo(mixed $other): int
    {
        if ($this->codec === null && $other instanceof self && $other->codec === null) {
            return $this->core->compareTo($other->getCore());
        }
        if ($this->codec === null && !$other instanceof UuidInterface) {
            return $this->core->compareTo($other);
        }
        $compare = \strcmp($this->toString(), self::stringify($other));

        return $compare <=> 0;
    }

    public function equals(?object $other): bool
    {
        if ($other instanceof UuidInterface) {
            return $this->codec === null && $other instanceof self && $other->codec === null
                ? $this->core->equals($other->getCore())
                : $this->toString() === $other->toString();
        }
        if ($other instanceof \FastUuid\Uuid) {
            return $this->codec === null
                ? $this->core->equals($other)
                : $this->toString() === $other->toString();
        }
        return false;
    }

    private static function stringify(mixed $other): string
    {
        if ($other instanceof UuidInterface || $other instanceof \FastUuid\Uuid) {
            return $other->toString();
        }

        return (string) $other;
    }

    // --- cold path: wrap into ramsey-shaped objects --------------------
    public function getHex(): Hexadecimal
    {
        return new Hexadecimal($this->codec === null
            ? $this->core->getHex()
            : \str_replace('-', '', $this->toString()));
    }
    public function getInteger(): IntegerObject
    {
        return new IntegerObject($this->codec === null
            ? $this->core->getInteger()
            : \FastUuid\Uuid::fromHexadecimal($this->getHex()->toString())->getInteger());
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
            // Re-attach the active presentation codec: dropping it here would
            // silently change getBytes()/toString() across a serialize round
            // trip (an OrderedTime column would read back network-order).
            $this->codec = self::factoryCodec();
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

    private static function factoryCodec(): ?CodecInterface
    {
        $factory = Uuid::getFactory();
        if (!$factory instanceof UuidFactory) {
            return null;
        }
        $codec = $factory->getCodec();

        return \get_class($codec) === StringCodec::class ? null : $codec;
    }

    private function assertCoreMatches(\FastUuid\Uuid $core): void
    {
        $expected = WrapperClass::for($core);
        if ($expected !== static::class) {
            throw new \FastUuid\Exception\InvalidArgumentException(\sprintf(
                '%s cannot wrap bytes that resolve to %s',
                static::class,
                $expected,
            ));
        }
    }
}
