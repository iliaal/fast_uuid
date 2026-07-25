<?php

declare(strict_types=1);

namespace FastUuid\Compat;

use FastUuid\Compat\Codec\CodecInterface;
use FastUuid\Compat\Codec\StringCodec;
use FastUuid\Compat\Internal\ConstructionToken;
use FastUuid\Compat\Internal\WrapperClass;
use FastUuid\Compat\Provider\DefaultRandomGenerator;
use FastUuid\Compat\Provider\DefaultTimeGenerator;
use FastUuid\Compat\Provider\NodeProviderInterface;
use FastUuid\Compat\Provider\RandomGeneratorInterface;
use FastUuid\Compat\Provider\RandomNodeProvider;
use FastUuid\Compat\Provider\TimeGeneratorInterface;
use FastUuid\Compat\Rfc4122\UuidV1;
use FastUuid\Compat\Rfc4122\UuidV2;
use FastUuid\Compat\Rfc4122\UuidV3;
use FastUuid\Compat\Rfc4122\UuidV4;
use FastUuid\Compat\Rfc4122\UuidV5;
use FastUuid\Compat\Rfc4122\UuidV6;
use FastUuid\Compat\Rfc4122\UuidV7;
use FastUuid\Compat\Rfc4122\UuidV8;
use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Compat\Type\Integer as IntegerObject;
use FastUuid\Compat\Validator\GenericValidator;
use FastUuid\Compat\Validator\ValidatorInterface;

/**
 * Generates and parses UUIDs by delegating to the fast_uuid C core, then wraps
 * each handle in the ramsey-shaped subclass matching its version and variant.
 *
 * The fast path is pure C. Swapping in a RandomGeneratorInterface,
 * TimeGeneratorInterface or NodeProviderInterface intentionally routes off the
 * C fast path where needed (ramsey-compat behaviour) so application-supplied
 * generators win for uuid1/uuid4/uuid6 and node providers also feed uuid2.
 */
class UuidFactory implements UuidFactoryInterface
{
    private ?RandomGeneratorInterface $randomGenerator = null;
    private ?TimeGeneratorInterface $timeGenerator = null;
    private ?NodeProviderInterface $nodeProvider = null;
    private ?ValidatorInterface $validator = null;
    private ?CodecInterface $codec = null;

    // Whether the application *set* a custom provider. The get*() lazy defaults
    // must not flip these, or merely inspecting a generator would route later
    // generation off the C fast path.
    private bool $customRandomGenerator = false;
    private bool $customTimeGenerator = false;
    private bool $customNodeProvider = false;

    public function getRandomGenerator(): RandomGeneratorInterface
    {
        return $this->randomGenerator ??= new DefaultRandomGenerator();
    }

    public function setRandomGenerator(RandomGeneratorInterface $generator): void
    {
        $this->randomGenerator = $generator;
        $this->customRandomGenerator = true;
    }

    public function getTimeGenerator(): TimeGeneratorInterface
    {
        return $this->timeGenerator ??= new DefaultTimeGenerator();
    }

    public function setTimeGenerator(TimeGeneratorInterface $generator): void
    {
        $this->timeGenerator = $generator;
        $this->customTimeGenerator = true;
    }

    public function getNodeProvider(): NodeProviderInterface
    {
        return $this->nodeProvider ??= new RandomNodeProvider();
    }

    public function setNodeProvider(NodeProviderInterface $provider): void
    {
        $this->nodeProvider = $provider;
        $this->customNodeProvider = true;
    }

    public function getValidator(): ValidatorInterface
    {
        return $this->validator ??= new GenericValidator();
    }

    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    public function getCodec(): CodecInterface
    {
        return $this->codec ??= new StringCodec();
    }

    public function setCodec(CodecInterface $codec): void
    {
        $this->codec = $codec;
    }

    public function uuid1(int|string|Hexadecimal|null $node = null, ?int $clockSeq = null): UuidInterface
    {
        $node = $this->resolveNode($node);
        if ($this->customTimeGenerator) {
            $b = self::applyVersionAndVariant($this->getTimeGenerator()->generate($node, $clockSeq), 1);
            return new UuidV1(
                \FastUuid\Uuid::fromBytes($b),
                $this->codec,
                ConstructionToken::Trusted,
            );
        }
        return new UuidV1(
            \FastUuid\Uuid::uuid1($node, $clockSeq),
            $this->codec,
            ConstructionToken::Trusted,
        );
    }

    public function uuid2(
        int $localDomain,
        int|string|IntegerObject|null $localIdentifier = null,
        int|string|Hexadecimal|null $node = null,
        ?int $clockSeq = null,
    ): UuidInterface {
        if ($localIdentifier instanceof IntegerObject) {
            $localIdentifier = $localIdentifier->toString();
        }
        $node = $this->resolveNode($node);
        if ($this->customTimeGenerator) {
            // Time generator supplies a v1-shaped layout; stamp version 2 and
            // overwrite time_low / clock_seq_low with DCE local id / domain.
            $b = self::applyVersionAndVariant($this->getTimeGenerator()->generate($node, $clockSeq), 2);
            $idBe = self::dceLocalIdBytes($localDomain, $localIdentifier);
            $b[0] = $idBe[0];
            $b[1] = $idBe[1];
            $b[2] = $idBe[2];
            $b[3] = $idBe[3];
            $b[9] = \chr($localDomain & 0xff);
            return new UuidV2(
                \FastUuid\Uuid::fromBytes($b),
                $this->codec,
                ConstructionToken::Trusted,
            );
        }
        return new UuidV2(
            \FastUuid\Uuid::uuid2($localDomain, $localIdentifier, $node, $clockSeq),
            $this->codec,
            ConstructionToken::Trusted,
        );
    }

    public function uuid3(UuidInterface|string $ns, string $name): UuidInterface
    {
        return new UuidV3(
            \FastUuid\Uuid::uuid3($this->coreNamespace($ns), $name),
            $this->codec,
            ConstructionToken::Trusted,
        );
    }

    public function uuid4(): UuidInterface
    {
        if ($this->customRandomGenerator) {
            $b = $this->getRandomGenerator()->generate(16);
            if (\strlen($b) !== 16) {
                throw new \FastUuid\Exception\InvalidArgumentException(
                    'Random generator must return exactly 16 bytes, got ' . \strlen($b)
                );
            }
            $b[6] = \chr((\ord($b[6]) & 0x0f) | 0x40);
            $b[8] = \chr((\ord($b[8]) & 0x3f) | 0x80);
            return new UuidV4(
                \FastUuid\Uuid::fromBytes($b),
                $this->codec,
                ConstructionToken::Trusted,
            );
        }
        return new UuidV4(\FastUuid\Uuid::uuid4(), $this->codec, ConstructionToken::Trusted);
    }

    public function uuid5(UuidInterface|string $ns, string $name): UuidInterface
    {
        return new UuidV5(
            \FastUuid\Uuid::uuid5($this->coreNamespace($ns), $name),
            $this->codec,
            ConstructionToken::Trusted,
        );
    }

    public function uuid6(int|string|Hexadecimal|null $node = null, ?int $clockSeq = null): UuidInterface
    {
        $node = $this->resolveNode($node);
        if ($this->customTimeGenerator) {
            // ramsey parity: v6 is built from the time generator's v1 bytes
            // with the timestamp reordered most-significant-first.
            $b = self::applyVersionAndVariant($this->getTimeGenerator()->generate($node, $clockSeq), 1);
            $v1 = new UuidV1(
                \FastUuid\Uuid::fromBytes($b),
                $this->codec,
                ConstructionToken::Trusted,
            );
            return UuidV6::fromUuidV1($v1);
        }
        return new UuidV6(
            \FastUuid\Uuid::uuid6($node, $clockSeq),
            $this->codec,
            ConstructionToken::Trusted,
        );
    }

    public function uuid7(int|\DateTimeInterface|null $dateTime = null): UuidInterface
    {
        if (!$this->customRandomGenerator) {
            return new UuidV7(
                \FastUuid\Uuid::uuid7($dateTime),
                $this->codec,
                ConstructionToken::Trusted,
            );
        }
        // Custom RNG: take the C core's time layout, then replace rand_a/rand_b
        // with bytes from the application generator (ramsey factory parity).
        $base = \FastUuid\Uuid::uuid7($dateTime);
        $bytes = $base->getBytes();
        $rnd = $this->getRandomGenerator()->generate(10);
        if (\strlen($rnd) !== 10) {
            throw new \FastUuid\Exception\InvalidArgumentException(
                'Random generator must return exactly 10 bytes for uuid7, got ' . \strlen($rnd)
            );
        }
        $randA = ((\ord($rnd[0]) << 4) | (\ord($rnd[1]) >> 4)) & 0x0fff;
        $bytes[6] = \chr(0x70 | (($randA >> 8) & 0x0f));
        $bytes[7] = \chr($randA & 0xff);
        $bytes[8] = \chr(0x80 | (\ord($rnd[2]) & 0x3f));
        for ($i = 0; $i < 7; $i++) {
            $bytes[9 + $i] = $rnd[3 + $i];
        }
        return new UuidV7(
            \FastUuid\Uuid::fromBytes($bytes),
            $this->codec,
            ConstructionToken::Trusted,
        );
    }

    public function uuid8(string $bytes): UuidInterface
    {
        return new UuidV8(\FastUuid\Uuid::uuid8($bytes), $this->codec, ConstructionToken::Trusted);
    }

    public function fromString(string $uuid): UuidInterface
    {
        return $this->getCodec()->decode($uuid);
    }

    public function fromBytes(string $bytes): UuidInterface
    {
        return $this->getCodec()->decodeBytes($bytes);
    }

    public function fromInteger(string $integer): UuidInterface
    {
        // Integer is always the RFC 128-bit network-order value. Never route
        // through a presentation codec (Guid/COMB would byte-swap the identity).
        return $this->wrap(\FastUuid\Uuid::fromInteger($integer));
    }

    public function fromHexadecimal(Hexadecimal|string $hex): UuidInterface
    {
        // Strict 32-hex input only: network-order hex identity, not Guid text.
        // Attach the factory codec for presentation only (toString/getBytes).
        $h = (string) $hex;
        if (\strlen($h) !== 32 || !\preg_match('/\A[0-9a-fA-F]{32}\z/', $h)) {
            throw new \FastUuid\Exception\InvalidArgumentException('Invalid hexadecimal UUID (expect 32 hex chars)');
        }
        return $this->wrap(\FastUuid\Uuid::fromHexadecimal($h));
    }

    public function fromDateTime(
        \DateTimeInterface $dateTime,
        int|string|Hexadecimal|null $node = null,
        ?int $clockSeq = null,
    ): UuidInterface {
        $node = $this->resolveNode($node);
        return new UuidV1(
            \FastUuid\Uuid::fromDateTime($dateTime, $node, $clockSeq),
            $this->codec,
            ConstructionToken::Trusted,
        );
    }

    /**
     * Wrap a C core handle in the matching ramsey-shaped subclass: nil/max
     * first, then the RFC 4122 per-version classes, falling back to the
     * nonstandard wrapper for non-RFC variants and unassigned versions.
     */
    public function wrap(\FastUuid\Uuid $core, ?CodecInterface $codec = null): UuidInterface
    {
        return WrapperClass::instantiateMapped($core, $codec ?? $this->codec);
    }

    private function coreNamespace(UuidInterface|string $ns): \FastUuid\Uuid
    {
        return $ns instanceof UuidInterface ? $ns->getCore() : $this->fromString($ns)->getCore();
    }

    private function resolveNode(int|string|Hexadecimal|null $node): int|string|null
    {
        if ($node === null && $this->customNodeProvider) {
            return \bin2hex($this->getNodeProvider()->getNode());
        }
        return $node instanceof Hexadecimal ? $node->toString() : $node;
    }

    /**
     * ramsey parity (uuidFromBytesAndVersion): the factory owns the version
     * and variant nibbles, so generators ported from ramsey — whose contract
     * leaves the nibbles to the factory — produce valid RFC bytes here too.
     */
    private static function applyVersionAndVariant(string $b, int $version): string
    {
        if (\strlen($b) !== 16) {
            throw new \FastUuid\Exception\InvalidArgumentException(
                'Time generator must return exactly 16 bytes, got ' . \strlen($b)
            );
        }
        $b[6] = \chr((\ord($b[6]) & 0x0f) | ($version << 4));
        $b[8] = \chr((\ord($b[8]) & 0x3f) | 0x80);
        return $b;
    }

    /** 4-byte big-endian DCE local identifier (0..2^32-1), portable on 32-bit PHP. */
    private static function dceLocalIdBytes(int $localDomain, int|string|null $id): string
    {
        if ($id === null) {
            if ($localDomain === 0 || $localDomain === 1) {
                $uid = $localDomain === 1 ? \getmygid() : \getmyuid();
                if ($uid === false) {
                    throw new \FastUuid\Exception\InvalidArgumentException(
                        'localIdentifier is required (could not read process uid/gid)'
                    );
                }
                $id = $uid;
            } else {
                throw new \FastUuid\Exception\InvalidArgumentException(
                    'localIdentifier is required for DCE domains other than PERSON (0) and GROUP (1)'
                );
            }
        }
        if (\is_string($id)) {
            if ($id === '' || ($id[0] === '0' && \strlen($id) > 1) || !\preg_match('/\A[0-9]+\z/', $id)) {
                throw new \FastUuid\Exception\InvalidArgumentException(
                    'localIdentifier string must be a canonical decimal number'
                );
            }
            if (\strlen($id) > 10 || (\strlen($id) === 10 && \strcmp($id, '4294967295') > 0)) {
                throw new \FastUuid\Exception\InvalidArgumentException(
                    'localIdentifier out of range (0..4294967295)'
                );
            }
            $hex = \str_pad(\base_convert($id, 10, 16), 8, '0', \STR_PAD_LEFT);
            return (string) \hex2bin($hex);
        }
        if ($id < 0 || $id > 0xFFFFFFFF) {
            throw new \FastUuid\Exception\InvalidArgumentException(
                'localIdentifier out of range (0..4294967295)'
            );
        }
        // pack('N') accepts the full unsigned range on both 32- and 64-bit.
        return \pack('N', $id);
    }
}
