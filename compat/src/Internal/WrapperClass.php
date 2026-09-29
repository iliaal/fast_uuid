<?php

declare(strict_types=1);

namespace FastUuid\Compat\Internal;

use FastUuid\Compat\Codec\CodecInterface;
use FastUuid\Compat\Guid\Guid;
use FastUuid\Compat\Rfc4122\UuidV1;
use FastUuid\Compat\Rfc4122\UuidV2;
use FastUuid\Compat\Rfc4122\UuidV3;
use FastUuid\Compat\Rfc4122\UuidV4;
use FastUuid\Compat\Rfc4122\UuidV5;
use FastUuid\Compat\Rfc4122\UuidV6;
use FastUuid\Compat\Rfc4122\UuidV7;
use FastUuid\Compat\Rfc4122\UuidV8;
use FastUuid\Compat\UuidInterface;

final class WrapperClass
{
    private const NIL_BYTES = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
    private const MAX_BYTES = "\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff";

    /** @var array<int, class-string<\FastUuid\Compat\AbstractUuid>> */
    public const VERSION_CLASSES = [
        1 => 'FastUuid\Compat\Rfc4122\UuidV1',
        2 => 'FastUuid\Compat\Rfc4122\UuidV2',
        3 => 'FastUuid\Compat\Rfc4122\UuidV3',
        4 => 'FastUuid\Compat\Rfc4122\UuidV4',
        5 => 'FastUuid\Compat\Rfc4122\UuidV5',
        6 => 'FastUuid\Compat\Rfc4122\UuidV6',
        7 => 'FastUuid\Compat\Rfc4122\UuidV7',
        8 => 'FastUuid\Compat\Rfc4122\UuidV8',
    ];

    /**
     * Inverse of VERSION_CLASSES, keyed by class name for the constructor's
     * fast path.
     *
     * @var array<class-string<\FastUuid\Compat\AbstractUuid>, int>
     */
    public const CLASS_VERSIONS = [
        'FastUuid\Compat\Rfc4122\UuidV1' => 1,
        'FastUuid\Compat\Rfc4122\UuidV2' => 2,
        'FastUuid\Compat\Rfc4122\UuidV3' => 3,
        'FastUuid\Compat\Rfc4122\UuidV4' => 4,
        'FastUuid\Compat\Rfc4122\UuidV5' => 5,
        'FastUuid\Compat\Rfc4122\UuidV6' => 6,
        'FastUuid\Compat\Rfc4122\UuidV7' => 7,
        'FastUuid\Compat\Rfc4122\UuidV8' => 8,
    ];

    /**
     * The final in-tree wrappers. Their getCore() is AbstractUuid's and
     * cannot be overridden, so it always returns the stored core.
     *
     * @var array<class-string<\FastUuid\Compat\AbstractUuid>, true>
     */
    public const FINAL_WRAPPERS = [
        'FastUuid\Compat\Rfc4122\UuidV1' => true,
        'FastUuid\Compat\Rfc4122\UuidV2' => true,
        'FastUuid\Compat\Rfc4122\UuidV3' => true,
        'FastUuid\Compat\Rfc4122\UuidV4' => true,
        'FastUuid\Compat\Rfc4122\UuidV5' => true,
        'FastUuid\Compat\Rfc4122\UuidV6' => true,
        'FastUuid\Compat\Rfc4122\UuidV7' => true,
        'FastUuid\Compat\Rfc4122\UuidV8' => true,
        'FastUuid\Compat\Rfc4122\NilUuid' => true,
        'FastUuid\Compat\Rfc4122\MaxUuid' => true,
        'FastUuid\Compat\Nonstandard\Uuid' => true,
    ];

    /**
     * The match arms, VERSION_CLASSES and CLASS_VERSIONS must name the same
     * class per version. Every other core (nil, max, nonstandard,
     * unassigned versions) resolves through for().
     */
    public static function instantiateMapped(
        \FastUuid\Uuid $core,
        ?CodecInterface $codec = null,
    ): UuidInterface {
        return match ($core->getVersion()) {
            4 => new UuidV4($core, $codec, ConstructionToken::Trusted),
            7 => new UuidV7($core, $codec, ConstructionToken::Trusted),
            1 => new UuidV1($core, $codec, ConstructionToken::Trusted),
            3 => new UuidV3($core, $codec, ConstructionToken::Trusted),
            5 => new UuidV5($core, $codec, ConstructionToken::Trusted),
            6 => new UuidV6($core, $codec, ConstructionToken::Trusted),
            2 => new UuidV2($core, $codec, ConstructionToken::Trusted),
            8 => new UuidV8($core, $codec, ConstructionToken::Trusted),
            default => self::instantiate(self::for($core), $core, $codec),
        };
    }

    /**
     * Resolves the single wrapper class a core may carry. Callers compare the
     * result against their own class rather than re-deriving version/variant.
     *
     * @return class-string<\FastUuid\Compat\AbstractUuid>
     */
    public static function for(\FastUuid\Uuid $core): string
    {
        // Versioned UUIDs avoid allocating getBytes() for nil/max checks.
        $version = $core->getVersion();
        if ($version !== null) {
            return self::VERSION_CLASSES[$version]
                ?? 'FastUuid\Compat\Nonstandard\Uuid';
        }

        $bytes = $core->getBytes();
        if ($bytes === self::NIL_BYTES) {
            return 'FastUuid\Compat\Rfc4122\NilUuid';
        }
        if ($bytes === self::MAX_BYTES) {
            return 'FastUuid\Compat\Rfc4122\MaxUuid';
        }
        return 'FastUuid\Compat\Nonstandard\Uuid';
    }

    // Rechecking in the constructor measured cheaper than a trusted-call cache.
    /** @param class-string<\FastUuid\Compat\AbstractUuid> $class */
    private static function instantiate(
        string $class,
        \FastUuid\Uuid $core,
        ?CodecInterface $codec,
    ): UuidInterface {
        return new $class($core, $codec, ConstructionToken::Trusted);
    }

    private static function returnTypeMayBeCore(
        \ReflectionType $type,
        string $declaringClass,
    ): bool {
        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $part) {
                if (self::returnTypeMayBeCore($part, $declaringClass)) {
                    return true;
                }
            }
            return false;
        }
        if ($type instanceof \ReflectionIntersectionType) {
            foreach ($type->getTypes() as $part) {
                if (!$part instanceof \ReflectionNamedType
                    || !self::namedTypeMayBeCore($part->getName(), $declaringClass)) {
                    return false;
                }
            }
            return true;
        }
        if (!$type instanceof \ReflectionNamedType) {
            return true;
        }
        if ($type->isBuiltin()) {
            return $type->getName() === 'mixed' || $type->getName() === 'object';
        }
        return self::namedTypeMayBeCore($type->getName(), $declaringClass);
    }

    private static function namedTypeMayBeCore(string $name, string $declaringClass): bool
    {
        if ($name === 'self' || $name === 'static') {
            $name = $declaringClass;
        } elseif ($name === 'parent') {
            $name = \get_parent_class($declaringClass) ?: '';
        }

        if ($name === '' || $name === \FastUuid\Uuid::class) return $name !== '';
        if (!\class_exists($name, false) && !\interface_exists($name, false)) return false;

        return \is_a($name, \FastUuid\Uuid::class, true)
            || \is_a(\FastUuid\Uuid::class, $name, true);
    }

    public static function coreBytes(UuidInterface $uuid): string
    {
        return self::coreFrom($uuid)->getBytes();
    }

    /** Resolve a UUID's network-order core without treating an incompatible
        optional getCore() name collision as the accessor protocol. */
    public static function coreFrom(UuidInterface $uuid): \FastUuid\Uuid
    {
        // Only classes whose getCore() cannot be overridden skip the
        // reflection walk. An open AbstractUuid subclass may narrow getCore()
        // to `never` or otherwise, and must be judged as any foreign class.
        if ($uuid instanceof Guid || isset(self::FINAL_WRAPPERS[$uuid::class])) {
            return $uuid->getCore();
        }
        if (\method_exists($uuid, 'getCore')) {
            $method = new \ReflectionMethod($uuid, 'getCore');
            if ($method->isPublic()
                && !$method->isStatic()
                && $method->getNumberOfRequiredParameters() === 0) {
                $returnType = $method->getReturnType();
                if ($returnType !== null && !self::returnTypeMayBeCore(
                    $returnType,
                    $method->getDeclaringClass()->getName(),
                )) {
                    return \FastUuid\Uuid::fromString($uuid->toString());
                }
                $core = $uuid->getCore();
                if ($core instanceof \FastUuid\Uuid) {
                    return $core;
                }
            }
        }

        return \FastUuid\Uuid::fromString($uuid->toString());
    }
}
