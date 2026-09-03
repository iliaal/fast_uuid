<?php

/** @generate-class-entries */

namespace FastUuid {

    interface UuidInterface extends \JsonSerializable, \Stringable {}

    interface UuidValueInterface extends UuidInterface
    {
        public function toString(): string;
        public function __toString(): string;
        public function getBytes(): string;
        public function toBytes(): string;
        public function getHex(): string;
        public function toHexadecimal(): string;
        public function getUrn(): string;
        public function toUrn(): string;
        public function getVersion(): ?int;
        public function getVariant(): int;
        public function getInteger(): string;
        public function toInteger(): string;
        public function getDateTime(): \DateTimeImmutable;
        public function getTimestampMillis(): int;
        public function getFields(): array;
        public function equals(mixed $other): bool;
        public function compareTo(mixed $other): int;
        public function jsonSerialize(): string;
        public function __serialize(): array;
        public function __unserialize(array $data): void;
    }

    /** @strict-properties */
    final class Uuid implements UuidValueInterface
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

        /** $node is a 12-hex string or int 0..2^48-1; $clockSeq 0..16383; null randomizes either. Wrong type raises TypeError, out-of-range raises InvalidArgumentException. */
        public static function uuid1(int|string|null $node = null, ?int $clockSeq = null): UuidValueInterface {}
        /** $localDomain 0..255 (PERSON=0, GROUP=1, ORG=2); out-of-range raises InvalidArgumentException, never truncates. $localIdentifier defaults to the process uid (PERSON) or gid (GROUP); it is required for other domains and on Windows. $node/$clockSeq as in uuid1. */
        public static function uuid2(int $localDomain, int|string|null $localIdentifier = null, int|string|null $node = null, ?int $clockSeq = null): UuidValueInterface {}
        /** $name over 16 MiB raises InvalidArgumentException; $ns parses tolerantly, InvalidArgumentException on failure. */
        public static function uuid3(UuidInterface|string $ns, string $name): UuidValueInterface {}
        public static function uuid4(): UuidValueInterface {}
        /** $name over 16 MiB raises InvalidArgumentException; $ns parses tolerantly, InvalidArgumentException on failure. */
        public static function uuid5(UuidInterface|string $ns, string $name): UuidValueInterface {}
        /** $node/$clockSeq as in uuid1. */
        public static function uuid6(int|string|null $node = null, ?int $clockSeq = null): UuidValueInterface {}
        /** $dateTime is an instant or a unix-millisecond int; null means now. */
        public static function uuid7(int|\DateTimeInterface|null $dateTime = null): UuidValueInterface {}
        /** $bytes must be exactly 16 bytes; InvalidArgumentException otherwise. */
        public static function uuid8(string $bytes): UuidValueInterface {}
        /** Tolerant: canonical 36, bare 32-hex, urn:uuid:/{} wrappers composed to depth 2. Throws InvalidUuidStringException on failure. */
        public static function fromString(string $uuid): UuidValueInterface {}
        public static function fromBytes(string $bytes): UuidValueInterface {}
        /** Network-order identity, immune to compat factory codecs. Throws InvalidArgumentException on failure. */
        public static function fromInteger(string $integer): UuidValueInterface {}
        /** 32 hex chars or a Stringable yielding them; network-order, immune to compat factory codecs. Throws InvalidUuidStringException on failure. */
        public static function fromHexadecimal(\Stringable|string $hex): UuidValueInterface {}
        /** $node/$clockSeq as in uuid1. */
        public static function fromDateTime(\DateTimeInterface $dateTime, int|string|null $node = null, ?int $clockSeq = null): UuidValueInterface {}
        /** Same tolerant set as fromString, without throwing. The compat GenericValidator accepts strict-canonical only, so swapping facades flips validation. */
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
        public function getVariant(): int {}
        public function getInteger(): string {}
        /** @alias FastUuid\Uuid::getInteger */
        public function toInteger(): string {}
        public function getDateTime(): \DateTimeImmutable {}
        public function getTimestampMillis(): int {}
        public function getFields(): array {}
        /** Returns false when $other cannot resolve to a UUID; only a throwing __toString propagates. */
        public function equals(mixed $other): bool {}
        /** Throws InvalidArgumentException ("Not comparable") when $other cannot resolve to a UUID. */
        public function compareTo(mixed $other): int {}
        public function jsonSerialize(): string {}
        public function __serialize(): array {}
        public function __unserialize(array $data): void {}
        public static function __set_state(array $an_array): static {}
    }
}

namespace FastUuid\Exception {
    interface UuidExceptionInterface extends \Throwable {}
    class InvalidArgumentException extends \InvalidArgumentException implements UuidExceptionInterface {}
    class InvalidUuidStringException extends InvalidArgumentException implements UuidExceptionInterface {}
    class UnsupportedOperationException extends \LogicException implements UuidExceptionInterface {}
}

namespace {
    function uuid_v1(): string {}
    function uuid_v1_bin(): string {}
    /** $name over 16 MiB raises InvalidArgumentException. */
    function uuid_v3(string $ns, string $name): string {}
    /** $name over 16 MiB raises InvalidArgumentException. */
    function uuid_v3_bin(string $ns, string $name): string {}
    function uuid_v4(): string {}
    function uuid_v4_bin(): string {}
    function uuid_v4_fast(): string {}
    function uuid_v4_fast_bin(): string {}
    /** $name over 16 MiB raises InvalidArgumentException. */
    function uuid_v5(string $ns, string $name): string {}
    /** $name over 16 MiB raises InvalidArgumentException. */
    function uuid_v5_bin(string $ns, string $name): string {}
    function uuid_v6(): string {}
    function uuid_v6_bin(): string {}
    function uuid_v7(): string {}
    function uuid_v7_bin(): string {}
    /** $unixMillis 0..2^48-1; out-of-range raises InvalidArgumentException. */
    function uuid_v7_at(int $unixMillis): string {}
    /** $unixMillis 0..2^48-1; out-of-range raises InvalidArgumentException. */
    function uuid_v7_at_bin(int $unixMillis): string {}
    function uuid_v8(string $bytes): string {}
    function uuid_v8_bin(string $bytes): string {}
    function uuid_v4_batch(int $count): array {}
    function uuid_v7_batch(int $count): array {}
    function uuid_v4_bin_batch(int $count): array {}
    function uuid_v7_bin_batch(int $count): array {}
    function uuid_to_bin(string $uuid): string {}
    function uuid_from_bin(string $bytes): string {}
    /** Same tolerant set as FastUuid\Uuid::isValid, without throwing. */
    function uuid_is_valid(string $uuid): bool {}
    function fast_uuid_random_bytes(int $length): string {}
}
