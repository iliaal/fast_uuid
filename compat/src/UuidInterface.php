<?php

declare(strict_types=1);

namespace FastUuid\Compat;

use FastUuid\Compat\Rfc4122\FieldsInterface;
use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Compat\Type\Integer as IntegerObject;

/** Mirrors the modern surface of Ramsey\Uuid\UuidInterface.
 *
 * equals() takes any value and returns false for non-UUID input rather than
 * throwing (a throwing __toString still propagates); only UUID values
 * resolving to the same 128 bits compare true. compareTo() accepts
 * UuidInterface and \FastUuid\Uuid and throws InvalidArgumentException
 * otherwise (ramsey would TypeError; the C core throws InvalidArgumentException).
 *
 * There is deliberately no getCore(): requiring it breaks third-party Ramsey
 * implementations and doubles. AbstractUuid and Guid expose getCore(), and
 * internals resolve any other implementation via its string form.
 */
interface UuidInterface extends \JsonSerializable, \Serializable, \Stringable
{
    public function compareTo(mixed $other): int;
    public function equals(mixed $other): bool;
    public function getBytes(): string;
    public function getFields(): FieldsInterface;
    public function getHex(): Hexadecimal;
    public function getInteger(): IntegerObject;
    public function getUrn(): string;
    public function getVariant(): int;
    public function getVersion(): ?int;
    public function getDateTime(): \DateTimeInterface;
    public function toString(): string;
    public function serialize(): string;
    public function unserialize(string $data): void;
}
