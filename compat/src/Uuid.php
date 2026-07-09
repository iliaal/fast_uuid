<?php

declare(strict_types=1);

namespace FastUuid\Compat;

use FastUuid\Compat\Type\Hexadecimal;

/**
 * Static facade mirroring Ramsey\Uuid\Uuid. Delegates to a swappable
 * UuidFactory; constants mirror the C core's RFC 9562 values so existing
 * `Ramsey\Uuid\Uuid::NAMESPACE_DNS`-style call sites port with a `use` swap.
 */
final class Uuid
{
    public const NIL  = '00000000-0000-0000-0000-000000000000';
    public const MAX  = 'ffffffff-ffff-ffff-ffff-ffffffffffff';
    public const NAMESPACE_DNS  = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
    public const NAMESPACE_URL  = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';
    public const NAMESPACE_OID  = '6ba7b812-9dad-11d1-80b4-00c04fd430c8';
    public const NAMESPACE_X500 = '6ba7b814-9dad-11d1-80b4-00c04fd430c8';

    public const DCE_DOMAIN_PERSON = 0;
    public const DCE_DOMAIN_GROUP  = 1;
    public const DCE_DOMAIN_ORG    = 2;

    public const DCE_DOMAIN_NAMES = [
        self::DCE_DOMAIN_PERSON => 'person',
        self::DCE_DOMAIN_GROUP  => 'group',
        self::DCE_DOMAIN_ORG    => 'org',
    ];

    // Variant constants (ramsey parity; values match getVariant()).
    public const RESERVED_NCS       = 0;
    public const RFC_4122           = 2;
    public const RFC_9562           = 2; // RFC 9562 obsoletes 4122; same variant value
    public const RESERVED_MICROSOFT = 6;
    public const RESERVED_FUTURE    = 7;

    // Version/type constants (ramsey parity; values match getVersion()).
    public const UUID_TYPE_TIME           = 1;
    public const UUID_TYPE_DCE_SECURITY   = 2;
    public const UUID_TYPE_HASH_MD5       = 3;
    public const UUID_TYPE_RANDOM         = 4;
    public const UUID_TYPE_HASH_SHA1      = 5;
    public const UUID_TYPE_REORDERED_TIME = 6;
    public const UUID_TYPE_UNIX_TIME      = 7;
    public const UUID_TYPE_CUSTOM         = 8;
    // Deprecated ramsey aliases, kept for drop-in source compatibility.
    public const UUID_TYPE_IDENTIFIER = 2; // @deprecated alias of UUID_TYPE_DCE_SECURITY
    public const UUID_TYPE_PEABODY    = 6; // @deprecated alias of UUID_TYPE_REORDERED_TIME

    public const VALID_PATTERN = '^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}$';

    private static ?UuidFactory $factory = null;

    public static function getFactory(): UuidFactory
    {
        return self::$factory ??= new UuidFactory();
    }

    public static function setFactory(UuidFactory $factory): void
    {
        self::$factory = $factory;
    }

    public static function uuid1(int|string|null $node = null, ?int $clockSeq = null): UuidInterface
    {
        return self::getFactory()->uuid1($node, $clockSeq);
    }

    public static function uuid2(
        int $localDomain,
        int|string|null $localIdentifier = null,
        int|string|null $node = null,
        ?int $clockSeq = null,
    ): UuidInterface {
        return self::getFactory()->uuid2($localDomain, $localIdentifier, $node, $clockSeq);
    }

    public static function uuid3(UuidInterface|string $ns, string $name): UuidInterface
    {
        return self::getFactory()->uuid3($ns, $name);
    }

    public static function uuid4(): UuidInterface
    {
        return self::getFactory()->uuid4();
    }

    public static function uuid5(UuidInterface|string $ns, string $name): UuidInterface
    {
        return self::getFactory()->uuid5($ns, $name);
    }

    public static function uuid6(int|string|null $node = null, ?int $clockSeq = null): UuidInterface
    {
        return self::getFactory()->uuid6($node, $clockSeq);
    }

    public static function uuid7(int|\DateTimeInterface|null $dateTime = null): UuidInterface
    {
        return self::getFactory()->uuid7($dateTime);
    }

    public static function uuid8(string $bytes): UuidInterface
    {
        return self::getFactory()->uuid8($bytes);
    }

    public static function fromString(string $uuid): UuidInterface
    {
        return self::getFactory()->fromString($uuid);
    }

    public static function fromBytes(string $bytes): UuidInterface
    {
        return self::getFactory()->fromBytes($bytes);
    }

    public static function fromInteger(string $integer): UuidInterface
    {
        return self::getFactory()->fromInteger($integer);
    }

    public static function fromHexadecimal(Hexadecimal|string $hex): UuidInterface
    {
        return self::getFactory()->fromHexadecimal($hex);
    }

    public static function fromDateTime(
        \DateTimeInterface $dateTime,
        int|string|null $node = null,
        ?int $clockSeq = null,
    ): UuidInterface {
        return self::getFactory()->fromDateTime($dateTime, $node, $clockSeq);
    }

    /**
     * Canonical-form validation via the factory's validator (RFC 4122 shape).
     * For the more permissive parser (bare 32-hex, urn:, braces) use
     * \FastUuid\Uuid::isValid().
     */
    public static function isValid(string $uuid): bool
    {
        return self::getFactory()->getValidator()->validate($uuid);
    }
}
