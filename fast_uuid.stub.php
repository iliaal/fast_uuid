<?php

/** @generate-class-entries */

namespace FastUuid {

    interface UuidInterface extends \JsonSerializable, \Stringable {}

    /** @strict-properties */
    final class Uuid implements UuidInterface
    {
        public const NIL = "00000000-0000-0000-0000-000000000000";
        public const MAX = "ffffffff-ffff-ffff-ffff-ffffffffffff";
        public const NAMESPACE_DNS  = "6ba7b810-9dad-11d1-80b4-00c04fd430c8";
        public const NAMESPACE_URL  = "6ba7b811-9dad-11d1-80b4-00c04fd430c8";
        public const NAMESPACE_OID  = "6ba7b812-9dad-11d1-80b4-00c04fd430c8";
        public const NAMESPACE_X500 = "6ba7b814-9dad-11d1-80b4-00c04fd430c8";

        public const DCE_DOMAIN_PERSON = 0;
        public const DCE_DOMAIN_GROUP  = 1;
        public const DCE_DOMAIN_ORG    = 2;

        public static function uuid1(int|string|null $node = null, ?int $clockSeq = null): UuidInterface {}
        public static function uuid2(int $localDomain, int|string|null $localIdentifier = null, int|string|null $node = null, ?int $clockSeq = null): UuidInterface {}
        public static function uuid3(UuidInterface|string $ns, string $name): UuidInterface {}
        public static function uuid4(): UuidInterface {}
        public static function uuid5(UuidInterface|string $ns, string $name): UuidInterface {}
        public static function uuid6(int|string|null $node = null, ?int $clockSeq = null): UuidInterface {}
        public static function uuid7(?\DateTimeInterface $dateTime = null): UuidInterface {}
        public static function uuid8(string $bytes): UuidInterface {}
        public static function fromString(string $uuid): UuidInterface {}
        public static function fromBytes(string $bytes): UuidInterface {}
        public static function fromInteger(string $integer): UuidInterface {}
        public static function fromHexadecimal(\Stringable|string $hex): UuidInterface {}
        public static function fromDateTime(\DateTimeInterface $dateTime, int|string|null $node = null, ?int $clockSeq = null): UuidInterface {}
        public static function isValid(string $uuid): bool {}

        private function __construct() {}
        public function toString(): string {}
        /** @alias FastUuid\Uuid::toString */
        public function __toString(): string {}
        public function getBytes(): string {}
        /** @alias FastUuid\Uuid::getBytes */
        public function toBytes(): string {}
        public function getHex(): string {}
        /** @alias FastUuid\Uuid::getHex */
        public function toHexadecimal(): string {}
        public function getUrn(): string {}
        /** @alias FastUuid\Uuid::getUrn */
        public function toUrn(): string {}
        public function getVersion(): ?int {}
        public function getVariant(): ?int {}
        public function getInteger(): string {}
        /** @alias FastUuid\Uuid::getInteger */
        public function toInteger(): string {}
        public function getDateTime(): \DateTimeImmutable {}
        public function getFields(): array {}
        public function equals(mixed $other): bool {}
        public function compareTo(mixed $other): int {}
        public function jsonSerialize(): string {}
        public function __serialize(): array {}
        public function __unserialize(array $data): void {}
    }
}

namespace FastUuid\Exception {
    class InvalidArgumentException extends \InvalidArgumentException {}
    class InvalidUuidStringException extends InvalidArgumentException {}
    class UnsupportedOperationException extends \RuntimeException {}
}

namespace {
    function uuid_v1(): string {}
    function uuid_v3(string $ns, string $name): string {}
    function uuid_v4(): string {}
    function uuid_v4_fast(): string {}
    function uuid_v5(string $ns, string $name): string {}
    function uuid_v6(): string {}
    function uuid_v7(): string {}
    function uuid_v8(string $bytes): string {}
    function uuid_to_bin(string $uuid): string {}
    function uuid_from_bin(string $bytes): string {}
    function uuid_is_valid(string $uuid): bool {}
    function fast_uuid_random_bytes(int $length): string {}
}
