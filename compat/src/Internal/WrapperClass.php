<?php

declare(strict_types=1);

namespace FastUuid\Compat\Internal;

use FastUuid\Compat\UuidInterface;

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

    /**
     * Resolves the single wrapper class a core may carry. Callers compare the
     * result against their own class rather than re-deriving version/variant.
     *
     * @return class-string<\FastUuid\Compat\AbstractUuid>
     */
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

    // The constructor re-derives this class from the same core, so each wrapper
    // costs two for() calls. Handing it a vouch to skip the second one measured
    // slower on every path (fu-r7h): a userland static call plus four static
    // property accesses costs more than the getVersion() call and array lookup
    // it avoids, and the factory paths, which construct wrappers directly and
    // never receive a vouch, pay the failed lookup on top of the full check.
    /** @param class-string<\FastUuid\Compat\AbstractUuid> $class */
    private static function instantiate(
        string $class,
        \FastUuid\Uuid $core,
        ?\FastUuid\Compat\Codec\CodecInterface $codec,
    ): \FastUuid\Compat\UuidInterface {
        return new $class($core, $codec, ConstructionToken::Trusted);
    }

    /**
     * Network-order bytes for any UuidInterface: zero-copy getCore() for
     * in-tree wrappers, string-form parse for third-party implementations
     * and doubles (which have no getCore() since CR-005). The string form is
     * assumed canonical (CR-007); codec-shaped text must go through the
     * owning codec's decode(), never here.
     */
    public static function coreBytes(UuidInterface $uuid): string
    {
        if (\method_exists($uuid, 'getCore')) {
            $core = $uuid->getCore();
            if ($core instanceof \FastUuid\Uuid) {
                return $core->getBytes();
            }
        }
        return \FastUuid\Uuid::fromString($uuid->toString())->getBytes();
    }
}
