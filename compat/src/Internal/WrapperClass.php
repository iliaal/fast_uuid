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

    public static function instantiateMapped(
        \FastUuid\Uuid $core,
        ?\FastUuid\Compat\Codec\CodecInterface $codec = null,
    ): \FastUuid\Compat\UuidInterface {
        return self::instantiate(self::for($core), $core, $codec);
    }

    // Runs on every wrapper construction: for() already resolves the one class
    // a core may wrap, so the version/variant re-derivation it replaced (an
    // array_search over the class names plus a getVariant() call) was redundant.
    public static function matches(\FastUuid\Uuid $core, string $class): bool
    {
        return self::for($core) === $class;
    }

    /** @return class-string<\FastUuid\Compat\AbstractUuid> */
    public static function for(\FastUuid\Uuid $core): string
    {
        // Hot path: RFC versions 1–8. getVersion() is null only for nil, max,
        // and non-RFC variants — skip the 16-byte getBytes() alloc for those.
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

    /** @param class-string<\FastUuid\Compat\AbstractUuid> $class */
    private static function instantiate(
        string $class,
        \FastUuid\Uuid $core,
        ?\FastUuid\Compat\Codec\CodecInterface $codec,
    ): \FastUuid\Compat\UuidInterface {
        return new $class($core, $codec, ConstructionToken::Trusted);
    }
}
