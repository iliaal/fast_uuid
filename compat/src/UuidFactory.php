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
        if ($node === null && $this->customNodeProvider) {
            $node = \bin2hex($this->getNodeProvider()->getNode());
        } else {
            $node = self::normalizeNode($node);
        }
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
        if ($node === null && $this->customNodeProvider) {
            $node = \bin2hex($this->getNodeProvider()->getNode());
        } else {
            $node = self::normalizeNode($node);
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
        if ($node === null && $this->customNodeProvider) {
            $node = \bin2hex($this->getNodeProvider()->getNode());
        } else {
            $node = self::normalizeNode($node);
        }
        if ($this->customTimeGenerator) {
            // ramsey parity: v6 is built from the time generator's v1 bytes
            // with the timestamp reordered most-significant-first.
            $b = self::applyVersionAndVariant($this->getTimeGenerator()->generate($node, $clockSeq), 1);
            $hex = \bin2hex(\substr($b, 0, 8));
            // 60-bit timestamp from the v1 layout: timeHi . timeMid . timeLow
            $ts = \substr($hex, 13, 3) . \substr($hex, 8, 4) . \substr($hex, 0, 8);
            $head = \hex2bin(\substr($ts, 0, 12) . '6' . \substr($ts, 12, 3));
            return new UuidV6(
                \FastUuid\Uuid::fromBytes($head . \substr($b, 8)),
                $this->codec,
                ConstructionToken::Trusted,
            );
        }
        return new UuidV6(
            \FastUuid\Uuid::uuid6($node, $clockSeq),
            $this->codec,
            ConstructionToken::Trusted,
        );
    }

    public function uuid7(int|\DateTimeInterface|null $dateTime = null): UuidInterface
    {
        return new UuidV7(
            \FastUuid\Uuid::uuid7($dateTime),
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
        $core = \FastUuid\Uuid::fromInteger($integer);
        if ($this->codec === null || \get_class($this->codec) === StringCodec::class) {
            return $this->wrap($core);
        }
        return $this->fromHexadecimal($core->getHex());
    }

    public function fromHexadecimal(Hexadecimal|string $hex): UuidInterface
    {
        // Strict 32-hex input only: unlike fromString(), a hexadecimal
        // identifier is not a canonical/URN/braced UUID string. Validate the
        // shape here, then dispatch through the codec's string decode() (ramsey
        // parity) so a custom codec sees the same entry point it does for hex.
        $h = (string) $hex;
        if (\strlen($h) !== 32 || !\preg_match('/\A[0-9a-fA-F]{32}\z/', $h)) {
            throw new \FastUuid\Exception\InvalidArgumentException('Invalid hexadecimal UUID (expect 32 hex chars)');
        }
        return $this->getCodec()->decode($h);
    }

    public function fromDateTime(
        \DateTimeInterface $dateTime,
        int|string|Hexadecimal|null $node = null,
        ?int $clockSeq = null,
    ): UuidInterface {
        if ($node === null && $this->customNodeProvider) {
            $node = \bin2hex($this->getNodeProvider()->getNode());
        } else {
            $node = self::normalizeNode($node);
        }
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

    private static function normalizeNode(int|string|Hexadecimal|null $node): int|string|null
    {
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
}
