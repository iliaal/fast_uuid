<?php

declare(strict_types=1);

namespace FastUuid\Compat\Rfc4122;

use FastUuid\Compat\Type\Hexadecimal;

/** Subset-compatible with Ramsey\Uuid\Rfc4122\FieldsInterface. */
interface FieldsInterface
{
    public function getBytes(): string;
    public function getClockSeq(): Hexadecimal;
    public function getClockSeqHiAndReserved(): Hexadecimal;
    public function getClockSeqLow(): Hexadecimal;
    public function getNode(): Hexadecimal;
    public function getTimeHiAndVersion(): Hexadecimal;
    public function getTimeLow(): Hexadecimal;
    public function getTimeMid(): Hexadecimal;
    public function getTimestamp(): Hexadecimal;
    public function getVersion(): ?int;
    public function getVariant(): int;
    public function isNil(): bool;
    public function isMax(): bool;
}
