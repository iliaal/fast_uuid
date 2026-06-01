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
 * C fast path (ramsey-compat behaviour) so application-supplied generators win.
 */
final class UuidFactory
{
    private ?RandomGeneratorInterface $randomGenerator = null;
    private ?TimeGeneratorInterface $timeGenerator = null;
    private ?NodeProviderInterface $nodeProvider = null;
    private ?ValidatorInterface $validator = null;
    private ?CodecInterface $codec = null;

    public function getRandomGenerator(): RandomGeneratorInterface
    {
        return $this->randomGenerator ??= new DefaultRandomGenerator();
    }

    public function setRandomGenerator(RandomGeneratorInterface $generator): void
    {
        $this->randomGenerator = $generator;
    }

    public function getTimeGenerator(): TimeGeneratorInterface
    {
        return $this->timeGenerator ??= new DefaultTimeGenerator();
    }

    public function setTimeGenerator(TimeGeneratorInterface $generator): void
    {
        $this->timeGenerator = $generator;
    }

    public function getNodeProvider(): NodeProviderInterface
    {
        return $this->nodeProvider ??= new RandomNodeProvider();
    }

    public function setNodeProvider(NodeProviderInterface $provider): void
    {
        $this->nodeProvider = $provider;
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
        if ($this->timeGenerator !== null || $this->nodeProvider !== null) {
            if ($node === null && $this->nodeProvider !== null) {
                $node = \bin2hex($this->nodeProvider->getNode());
            }
            return $this->wrap(\FastUuid\Uuid::fromBytes($this->getTimeGenerator()->generate($node, $clockSeq)));
        }
        return $this->wrap(\FastUuid\Uuid::uuid1($node, $clockSeq));
    }

    public function uuid2(
        int $localDomain,
        int|string|null $localIdentifier = null,
        int|string|null $node = null,
        ?int $clockSeq = null,
    ): UuidInterface {
        return $this->wrap(\FastUuid\Uuid::uuid2($localDomain, $localIdentifier, $node, $clockSeq));
    }

    public function uuid3(UuidInterface|string $ns, string $name): UuidInterface
    {
        return $this->wrap(\FastUuid\Uuid::uuid3($this->coreNamespace($ns), $name));
    }

    public function uuid4(): UuidInterface
    {
        if ($this->randomGenerator !== null) {
            $b = $this->randomGenerator->generate(16);
            $b[6] = \chr((\ord($b[6]) & 0x0f) | 0x40);
            $b[8] = \chr((\ord($b[8]) & 0x3f) | 0x80);
            return $this->wrap(\FastUuid\Uuid::fromBytes($b));
        }
        return $this->wrap(\FastUuid\Uuid::uuid4());
    }

    public function uuid5(UuidInterface|string $ns, string $name): UuidInterface
    {
        return $this->wrap(\FastUuid\Uuid::uuid5($this->coreNamespace($ns), $name));
    }

    public function uuid6(int|string|null $node = null, ?int $clockSeq = null): UuidInterface
    {
        if ($node === null && $this->nodeProvider !== null) {
            $node = \bin2hex($this->nodeProvider->getNode());
        }
        return $this->wrap(\FastUuid\Uuid::uuid6($node, $clockSeq));
    }

    public function uuid7(?\DateTimeInterface $dateTime = null): UuidInterface
    {
        return $this->wrap(\FastUuid\Uuid::uuid7($dateTime));
    }

    public function uuid8(string $bytes): UuidInterface
    {
        return $this->wrap(\FastUuid\Uuid::uuid8($bytes));
    }

    public function fromString(string $uuid): UuidInterface
    {
        return $this->wrap(\FastUuid\Uuid::fromString($uuid));
    }

    public function fromBytes(string $bytes): UuidInterface
    {
        return $this->wrap(\FastUuid\Uuid::fromBytes($bytes));
    }

    public function fromInteger(string $integer): UuidInterface
    {
        return $this->wrap(\FastUuid\Uuid::fromInteger($integer));
    }

    public function fromHexadecimal(Hexadecimal|string $hex): UuidInterface
    {
        return $this->wrap(\FastUuid\Uuid::fromHexadecimal((string) $hex));
    }

    public function fromDateTime(
        \DateTimeInterface $dateTime,
        int|string|null $node = null,
        ?int $clockSeq = null,
    ): UuidInterface {
        return $this->wrap(\FastUuid\Uuid::fromDateTime($dateTime, $node, $clockSeq));
    }

    /**
     * Wrap a C core handle in the matching ramsey-shaped subclass: nil/max
     * first, then the RFC 4122 per-version classes, falling back to the
     * nonstandard wrapper for non-RFC variants and unassigned versions.
     */
    public function wrap(\FastUuid\Uuid $core): UuidInterface
    {
        $bytes = $core->getBytes();
        if ($bytes === \str_repeat("\x00", 16)) {
            return new NilUuid($core);
        }
        if ($bytes === \str_repeat("\xff", 16)) {
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

    private function coreNamespace(UuidInterface|string $ns): \FastUuid\Uuid|string
    {
        return $ns instanceof UuidInterface ? $ns->getCore() : $ns;
    }
}
