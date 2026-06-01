<?php

declare(strict_types=1);

namespace FastUuid\Compat;

use FastUuid\Compat\Rfc4122\FieldsInterface;
use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Compat\Type\Integer as IntegerObject;

/** Mirrors the modern surface of Ramsey\Uuid\UuidInterface. */
interface UuidInterface extends \JsonSerializable, \Stringable
{
    public function compareTo(UuidInterface $other): int;
    public function equals(?object $other): bool;
    public function getBytes(): string;
    public function getFields(): FieldsInterface;
    public function getHex(): Hexadecimal;
    public function getInteger(): IntegerObject;
    public function getUrn(): string;
    public function getVariant(): ?int;
    public function getVersion(): ?int;
    public function getDateTime(): \DateTimeInterface;
    public function toString(): string;

    /** Access to the underlying extension handle for hot paths. */
    public function getCore(): \FastUuid\Uuid;
}
