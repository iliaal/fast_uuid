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

    /**
     * Byte-order comparison over the core handles, independent of any
     * presentation codec. Only UuidInterface and \FastUuid\Uuid are accepted;
     * anything else (strings, scalars, null) throws InvalidArgumentException
     * so the outcome never depends on codec state.
     */
    public function compareTo(mixed $other): int
    {
        if ($other instanceof UuidInterface) {
            if ($other instanceof self && $this->codec === null && $other->codec === null) {
                return $this->core->compareTo($other->getCore());
            }
            return $this->core->compareTo(self::coreOf($other));
        }
        if ($other instanceof \FastUuid\Uuid) {
            return $this->core->compareTo($other);
        }
        throw new \FastUuid\Exception\InvalidArgumentException(
            'Not comparable: expected UuidInterface or FastUuid\\Uuid'
        );
    }

    /**
     * Never throws on scalars: only UUID values resolving to the same 128
     * bits compare true. Strings and Stringables delegate to the core's
     * tolerant parser (unparseable input is false, not a throw).
     */
    public function equals(mixed $other): bool
    {
        if ($other instanceof UuidInterface) {
            if ($other instanceof self && $this->codec === null && $other->codec === null) {
                return $this->core->equals($other->getCore());
            }
            try {
                return $this->core->equals(self::coreOf($other));
            } catch (\FastUuid\Exception\InvalidArgumentException) {
                return false;
            }
        }
        if ($other instanceof \FastUuid\Uuid || \is_string($other) || $other instanceof \Stringable) {
            return $this->core->equals($other);
        }
        return false;
    }

    /**
     * Resolve any UuidInterface to its core handle: fast path for our own
     * wrappers, string-form parse for third-party Ramsey implementations
     * and doubles (which have no getCore()).
     */
    private static function coreOf(UuidInterface $other): \FastUuid\Uuid
    {
        if (\method_exists($other, 'getCore')) {
            $core = $other->getCore();
            if ($core instanceof \FastUuid\Uuid) {
                return $core;
            }
        }
        try {
            return \FastUuid\Uuid::fromString($other->toString());
        } catch (\FastUuid\Exception\InvalidArgumentException $e) {
            throw new \FastUuid\Exception\InvalidArgumentException(
                'Not comparable: UUID string form is unparseable',
                0,
                $e,
            );
        }
    }

    // --- cold path: wrap into ramsey-shaped objects --------------------
    public function getHex(): Hexadecimal
    {
        return new Hexadecimal($this->codec === null
            ? $this->core->getHex()
            : \str_replace('-', '', $this->toString()));
    }
    /**
     * Always the RFC 128-bit network-order value from the core bytes.
     * Deriving from getHex() would read presentation-permuted text under
     * byte-reordering codecs (Guid/COMB) and silently break
     * fromInteger(getInteger()) round-trips.
     */
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
        // Parse natively for both payload shapes: never route through the
        // factory codec, whose decode()/decodeBytes() may reorder bytes (COMB)
        // or byte-swap fields (Guid) and silently change the identity.
        $core = \strlen($data) === 16
            ? \FastUuid\Uuid::fromBytes($data)
            : \FastUuid\Uuid::fromString($data);
        $this->assertCoreMatches($core);
        $this->core = $core;
        // Re-attach the active presentation codec: dropping it here would
        // silently change getBytes()/toString() across a serialize round
        // trip (an OrderedTime column would read back network-order).
        $this->codec = self::factoryCodec();
        $this->canonical = null;
    }

    public function __serialize(): array { return ['bytes' => $this->serialize()]; }
    public function __unserialize(array $data): void
    {
        if (!isset($data['bytes'])) {
            throw new \ValueError(\sprintf('%s(): Argument #1 ($data) is invalid', __METHOD__));
        }
        $this->unserialize($data['bytes']);
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
