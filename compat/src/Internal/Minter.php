<?php

declare(strict_types=1);

namespace FastUuid\Compat\Internal;

use FastUuid\Compat\AbstractUuid;
use FastUuid\Compat\Codec\CodecInterface;
use FastUuid\Compat\Codec\StringCodec;
use FastUuid\Compat\Rfc4122\UuidV1;
use FastUuid\Compat\Rfc4122\UuidV2;
use FastUuid\Compat\Rfc4122\UuidV3;
use FastUuid\Compat\Rfc4122\UuidV4;
use FastUuid\Compat\Rfc4122\UuidV5;
use FastUuid\Compat\Rfc4122\UuidV6;
use FastUuid\Compat\Rfc4122\UuidV7;
use FastUuid\Compat\Rfc4122\UuidV8;
use FastUuid\Compat\UuidInterface;

/**
 * Builds compat wrappers by cloning a cached constructor-less prototype per
 * class and attaching the core. Kept off AbstractUuid so a user subclass may
 * declare statics with these names and any signature. Extending WrapperState
 * gives it write access to the wrapper's protected fields; it is never
 * instantiated.
 *
 * @internal
 */
final class Minter extends WrapperState
{
    /** @var array<int|string, AbstractUuid> */
    private static array $prototypes = [];

    private function __construct()
    {
    }

    /** @param class-string<AbstractUuid> $class */
    private static function cloneWrapper(int|string $key, string $class, \FastUuid\Uuid $core, ?CodecInterface $codec): AbstractUuid
    {
        $proto = self::$prototypes[$key] ??= (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        $obj = clone $proto;
        $obj->core = $core;
        if ($codec !== null && \get_class($codec) !== StringCodec::class) {
            $obj->codec = $codec;
        }
        return $obj;
    }

    public static function wrapCore(\FastUuid\Uuid $core, ?CodecInterface $codec = null): UuidInterface
    {
        return match ($core->getVersion()) {
            4 => self::cloneWrapper(4, UuidV4::class, $core, $codec),
            7 => self::cloneWrapper(7, UuidV7::class, $core, $codec),
            1 => self::cloneWrapper(1, UuidV1::class, $core, $codec),
            3 => self::cloneWrapper(3, UuidV3::class, $core, $codec),
            5 => self::cloneWrapper(5, UuidV5::class, $core, $codec),
            6 => self::cloneWrapper(6, UuidV6::class, $core, $codec),
            2 => self::cloneWrapper(2, UuidV2::class, $core, $codec),
            8 => self::cloneWrapper(8, UuidV8::class, $core, $codec),
            default => self::wrapSpecial($core, $codec),
        };
    }

    private static function wrapSpecial(\FastUuid\Uuid $core, ?CodecInterface $codec): UuidInterface
    {
        $class = WrapperClass::for($core);
        return self::cloneWrapper($class, $class, $core, $codec);
    }

    public static function mintV4(?CodecInterface $codec = null): UuidV4
    {
        $proto = self::$prototypes[4] ??= (new \ReflectionClass(UuidV4::class))->newInstanceWithoutConstructor();
        $obj = clone $proto;
        $obj->core = \FastUuid\Uuid::uuid4();
        if ($codec !== null && \get_class($codec) !== StringCodec::class) {
            $obj->codec = $codec;
        }
        return $obj;
    }

    public static function mintV7(int|\DateTimeInterface|null $dateTime = null, ?CodecInterface $codec = null): UuidV7
    {
        $proto = self::$prototypes[7] ??= (new \ReflectionClass(UuidV7::class))->newInstanceWithoutConstructor();
        $obj = clone $proto;
        $obj->core = \FastUuid\Uuid::uuid7($dateTime);
        if ($codec !== null && \get_class($codec) !== StringCodec::class) {
            $obj->codec = $codec;
        }
        return $obj;
    }

    public static function mintV1(int|string|null $node = null, ?int $clockSeq = null, ?CodecInterface $codec = null): UuidV1
    {
        /** @var UuidV1 */
        return self::cloneWrapper(1, UuidV1::class, \FastUuid\Uuid::uuid1($node, $clockSeq), $codec);
    }

    public static function mintV2(
        int $localDomain,
        int|string|null $localIdentifier = null,
        int|string|null $node = null,
        ?int $clockSeq = null,
        ?CodecInterface $codec = null,
    ): UuidV2 {
        /** @var UuidV2 */
        return self::cloneWrapper(
            2,
            UuidV2::class,
            \FastUuid\Uuid::uuid2($localDomain, $localIdentifier, $node, $clockSeq),
            $codec,
        );
    }

    public static function mintV3(\FastUuid\Uuid|string $ns, string $name, ?CodecInterface $codec = null): UuidV3
    {
        /** @var UuidV3 */
        return self::cloneWrapper(3, UuidV3::class, \FastUuid\Uuid::uuid3($ns, $name), $codec);
    }

    public static function mintV5(\FastUuid\Uuid|string $ns, string $name, ?CodecInterface $codec = null): UuidV5
    {
        /** @var UuidV5 */
        return self::cloneWrapper(5, UuidV5::class, \FastUuid\Uuid::uuid5($ns, $name), $codec);
    }

    public static function mintV6(int|string|null $node = null, ?int $clockSeq = null, ?CodecInterface $codec = null): UuidV6
    {
        /** @var UuidV6 */
        return self::cloneWrapper(6, UuidV6::class, \FastUuid\Uuid::uuid6($node, $clockSeq), $codec);
    }

    public static function mintV8(string $bytes, ?CodecInterface $codec = null): UuidV8
    {
        /** @var UuidV8 */
        return self::cloneWrapper(8, UuidV8::class, \FastUuid\Uuid::uuid8($bytes), $codec);
    }

    public static function mintFromDateTime(
        \DateTimeInterface $dateTime,
        int|string|null $node = null,
        ?int $clockSeq = null,
        ?CodecInterface $codec = null,
    ): UuidV1 {
        /** @var UuidV1 */
        return self::cloneWrapper(1, UuidV1::class, \FastUuid\Uuid::fromDateTime($dateTime, $node, $clockSeq), $codec);
    }
}
