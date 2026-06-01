<?php

declare(strict_types=1);

namespace FastUuid\Compat;

use FastUuid\Compat\Nonstandard\Uuid as NonstandardUuid;
use FastUuid\Compat\Rfc4122\MaxUuid;
use FastUuid\Compat\Rfc4122\NilUuid;
use FastUuid\Compat\Rfc4122\UuidV1;
use FastUuid\Compat\Rfc4122\UuidV3;
use FastUuid\Compat\Rfc4122\UuidV4;
use FastUuid\Compat\Rfc4122\UuidV5;
use FastUuid\Compat\Rfc4122\UuidV6;
use FastUuid\Compat\Rfc4122\UuidV7;
use FastUuid\Compat\Rfc4122\UuidV8;
use FastUuid\Compat\Type\Hexadecimal;

/**
 * Generates and parses UUIDs by delegating to the fast_uuid C core, then wraps
 * each resulting handle in the ramsey-shaped subclass that matches its version
 * and variant. All generation/formatting work happens in C; this layer only
 * picks the wrapper type.
 */
final class UuidFactory
{
    public function uuid1(int|string|null $node = null, ?int $clockSeq = null): UuidInterface
    {
        return $this->wrap(\FastUuid\Uuid::uuid1($node, $clockSeq));
    }

    public function uuid3(UuidInterface|string $ns, string $name): UuidInterface
    {
        return $this->wrap(\FastUuid\Uuid::uuid3($this->coreNamespace($ns), $name));
    }

    public function uuid4(): UuidInterface
    {
        return $this->wrap(\FastUuid\Uuid::uuid4());
    }

    public function uuid5(UuidInterface|string $ns, string $name): UuidInterface
    {
        return $this->wrap(\FastUuid\Uuid::uuid5($this->coreNamespace($ns), $name));
    }

    public function uuid6(int|string|null $node = null, ?int $clockSeq = null): UuidInterface
    {
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
     * nonstandard wrapper for non-RFC variants and unassigned versions
     * (including v2/DCE, which has no dedicated class yet).
     */
    public function wrap(\FastUuid\Uuid $core): UuidInterface
    {
        $bytes = $core->getBytes();
        if ($bytes === str_repeat("\x00", 16)) {
            return new NilUuid($core);
        }
        if ($bytes === str_repeat("\xff", 16)) {
            return new MaxUuid($core);
        }

        if ($core->getVariant() === 2) {
            $class = match ($core->getVersion()) {
                1 => UuidV1::class,
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
