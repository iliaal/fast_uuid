<?php

declare(strict_types=1);

namespace FastUuid\Compat;

use FastUuid\Compat\Codec\CodecInterface;
use FastUuid\Compat\Codec\StringCodec;
use FastUuid\Compat\Nonstandard\Uuid as NonstandardUuid;
use FastUuid\Compat\Provider\DefaultRandomGenerator;
use FastUuid\Compat\Provider\DefaultTimeGenerator;
use FastUuid\Compat\Provider\NodeProviderInterface;
use FastUuid\Compat\Provider\RandomGeneratorInterface;
use FastUuid\Compat\Provider\RandomNodeProvider;
use FastUuid\Compat\Provider\TimeGeneratorInterface;
use FastUuid\Compat\Rfc4122\MaxUuid;
use FastUuid\Compat\Rfc4122\NilUuid;
use FastUuid\Compat\Rfc4122\UuidV1;
use FastUuid\Compat\Rfc4122\UuidV2;
use FastUuid\Compat\Rfc4122\UuidV3;
use FastUuid\Compat\Rfc4122\UuidV4;
use FastUuid\Compat\Rfc4122\UuidV5;
use FastUuid\Compat\Rfc4122\UuidV6;
use FastUuid\Compat\Rfc4122\UuidV7;
use FastUuid\Compat\Rfc4122\UuidV8;
use FastUuid\Compat\Type\Hexadecimal;
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
final class UuidFactory
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

    public function uuid1(int|string|null $node = null, ?int $clockSeq = null): UuidInterface
    {
        if ($this->customTimeGenerator || $this->customNodeProvider) {
            if ($node === null && $this->customNodeProvider) {
                $node = \bin2hex($this->getNodeProvider()->getNode());
            }
            $b = self::applyVersionAndVariant($this->getTimeGenerator()->generate($node, $clockSeq), 1);
            return $this->wrapKnownVersion(\FastUuid\Uuid::fromBytes($b), 1);
        }
        return $this->wrapKnownVersion(\FastUuid\Uuid::uuid1($node, $clockSeq), 1);
    }

    public function uuid2(
        int $localDomain,
        int|string|null $localIdentifier = null,
        int|string|null $node = null,
        ?int $clockSeq = null,
    ): UuidInterface {
        if ($node === null && $this->customNodeProvider) {
            $node = \bin2hex($this->getNodeProvider()->getNode());
        }
        return $this->wrapKnownVersion(\FastUuid\Uuid::uuid2($localDomain, $localIdentifier, $node, $clockSeq), 2);
    }

    public function uuid3(UuidInterface|string $ns, string $name): UuidInterface
    {
        return $this->wrapKnownVersion(\FastUuid\Uuid::uuid3($this->coreNamespace($ns), $name), 3);
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
            return $this->wrapKnownVersion(\FastUuid\Uuid::fromBytes($b), 4);
        }
        return $this->wrapKnownVersion(\FastUuid\Uuid::uuid4(), 4);
    }

    public function uuid5(UuidInterface|string $ns, string $name): UuidInterface
    {
        return $this->wrapKnownVersion(\FastUuid\Uuid::uuid5($this->coreNamespace($ns), $name), 5);
    }

    public function uuid6(int|string|null $node = null, ?int $clockSeq = null): UuidInterface
    {
        if ($node === null && $this->customNodeProvider) {
            $node = \bin2hex($this->getNodeProvider()->getNode());
        }
        if ($this->customTimeGenerator) {
            // ramsey parity: v6 is built from the time generator's v1 bytes
            // with the timestamp reordered most-significant-first.
            $b = self::applyVersionAndVariant($this->getTimeGenerator()->generate($node, $clockSeq), 1);
            $hex = \bin2hex(\substr($b, 0, 8));
            // 60-bit timestamp from the v1 layout: timeHi . timeMid . timeLow
            $ts = \substr($hex, 13, 3) . \substr($hex, 8, 4) . \substr($hex, 0, 8);
            $head = \hex2bin(\substr($ts, 0, 12) . '6' . \substr($ts, 12, 3));
            return $this->wrapKnownVersion(\FastUuid\Uuid::fromBytes($head . \substr($b, 8)), 6);
        }
        return $this->wrapKnownVersion(\FastUuid\Uuid::uuid6($node, $clockSeq), 6);
    }

    public function uuid7(int|\DateTimeInterface|null $dateTime = null): UuidInterface
    {
        return $this->wrapKnownVersion(\FastUuid\Uuid::uuid7($dateTime), 7);
    }

    public function uuid8(string $bytes): UuidInterface
    {
        return $this->wrapKnownVersion(\FastUuid\Uuid::uuid8($bytes), 8);
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
        return $this->fromHexadecimal(\FastUuid\Uuid::fromInteger($integer)->getHex());
    }

    public function fromHexadecimal(Hexadecimal|string $hex): UuidInterface
    {
        return $this->getCodec()->decode((string) $hex);
    }

    public function fromDateTime(
        \DateTimeInterface $dateTime,
        int|string|null $node = null,
        ?int $clockSeq = null,
    ): UuidInterface {
        if ($node === null && $this->customNodeProvider) {
            $node = \bin2hex($this->getNodeProvider()->getNode());
        }
        return $this->wrapKnownVersion(\FastUuid\Uuid::fromDateTime($dateTime, $node, $clockSeq), 1);
    }

    /**
     * Wrap a C core handle in the matching ramsey-shaped subclass: nil/max
     * first, then the RFC 4122 per-version classes, falling back to the
     * nonstandard wrapper for non-RFC variants and unassigned versions.
     */
    private const NIL_BYTES = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
    private const MAX_BYTES = "\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff";

    public function wrap(\FastUuid\Uuid $core): UuidInterface
    {
        $bytes = $core->getBytes();
        if ($bytes === self::NIL_BYTES) {
            return new NilUuid($core);
        }
        if ($bytes === self::MAX_BYTES) {
            return new MaxUuid($core);
        }

        if ($core->getVariant() === 2) {
            $class = match ($core->getVersion()) {
                1 => UuidV1::class,
                2 => UuidV2::class,
                3 => UuidV3::class,
                4 => UuidV4::class,
                5 => UuidV5::class,
                6 => UuidV6::class,
                7 => UuidV7::class,
                8 => UuidV8::class,
                default => null,
            };
            if ($class !== null) {
                return new $class($core);
            }
        }

        return new NonstandardUuid($core);
    }

    private function wrapKnownVersion(\FastUuid\Uuid $core, int $version): UuidInterface
    {
        return match ($version) {
            1 => new UuidV1($core),
            2 => new UuidV2($core),
            3 => new UuidV3($core),
            4 => new UuidV4($core),
            5 => new UuidV5($core),
            6 => new UuidV6($core),
            7 => new UuidV7($core),
            8 => new UuidV8($core),
            default => throw new \FastUuid\Exception\InvalidArgumentException('Unknown UUID version'),
        };
    }

    private function coreNamespace(UuidInterface|string $ns): \FastUuid\Uuid|string
    {
        return $ns instanceof UuidInterface ? $ns->getCore() : $ns;
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
