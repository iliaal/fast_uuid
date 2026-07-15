<?php

declare(strict_types=1);

namespace FastUuid\Compat\Internal;

final class WrapperClass
{
    private const NIL_BYTES = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
    private const MAX_BYTES = "\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff";

    /** @var array<int, class-string<\FastUuid\Compat\AbstractUuid>> */
    private const VERSION_CLASSES = [
        1 => 'FastUuid\Compat\Rfc4122\UuidV1',
        2 => 'FastUuid\Compat\Rfc4122\UuidV2',
        3 => 'FastUuid\Compat\Rfc4122\UuidV3',
        4 => 'FastUuid\Compat\Rfc4122\UuidV4',
        5 => 'FastUuid\Compat\Rfc4122\UuidV5',
        6 => 'FastUuid\Compat\Rfc4122\UuidV6',
        7 => 'FastUuid\Compat\Rfc4122\UuidV7',
        8 => 'FastUuid\Compat\Rfc4122\UuidV8',
    ];

    private const CLASS_VERSIONS = [
        'FastUuid\Compat\Rfc4122\UuidV1' => 1,
        'FastUuid\Compat\Rfc4122\UuidV2' => 2,
        'FastUuid\Compat\Rfc4122\UuidV3' => 3,
        'FastUuid\Compat\Rfc4122\UuidV4' => 4,
        'FastUuid\Compat\Rfc4122\UuidV5' => 5,
        'FastUuid\Compat\Rfc4122\UuidV6' => 6,
        'FastUuid\Compat\Rfc4122\UuidV7' => 7,
        'FastUuid\Compat\Rfc4122\UuidV8' => 8,
    ];

    public static function instantiateMapped(
        \FastUuid\Uuid $core,
        ?\FastUuid\Compat\Codec\CodecInterface $codec = null,
    ): \FastUuid\Compat\UuidInterface {
        return self::instantiate(self::for($core), $core, $codec);
    }

    public static function matches(\FastUuid\Uuid $core, string $class): bool
    {
        if (isset(self::CLASS_VERSIONS[$class])) {
            return $core->getVariant() === 2
                && $core->getVersion() === self::CLASS_VERSIONS[$class];
        }

        return self::for($core) === $class;
    }

    /** @return class-string<\FastUuid\Compat\AbstractUuid> */
    public static function for(\FastUuid\Uuid $core): string
    {
        $bytes = $core->getBytes();
        if ($bytes === self::NIL_BYTES) {
            return 'FastUuid\Compat\Rfc4122\NilUuid';
        }
        if ($bytes === self::MAX_BYTES) {
            return 'FastUuid\Compat\Rfc4122\MaxUuid';
        }
        if ($core->getVariant() !== 2) {
            return 'FastUuid\Compat\Nonstandard\Uuid';
        }

        return self::VERSION_CLASSES[$core->getVersion()]
            ?? 'FastUuid\Compat\Nonstandard\Uuid';
    }

    /** @param class-string<\FastUuid\Compat\AbstractUuid> $class */
    private static function instantiate(
        string $class,
        \FastUuid\Uuid $core,
        ?\FastUuid\Compat\Codec\CodecInterface $codec,
    ): \FastUuid\Compat\UuidInterface {
        return new $class($core, $codec, ConstructionToken::Trusted);
    }
}
