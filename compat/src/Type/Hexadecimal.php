<?php

declare(strict_types=1);

namespace FastUuid\Compat\Type;

use FastUuid\Exception\InvalidArgumentException;

/** Mirrors Ramsey\Uuid\Type\Hexadecimal. */
final class Hexadecimal implements \JsonSerializable, \Stringable
{
    private string $hex;

    public function __construct(string $value)
    {
        $v = str_starts_with($value, '0x') ? substr($value, 2) : $value;
        if (!\preg_match('/^[0-9a-fA-F]+$/D', $v)) {
            throw new InvalidArgumentException('Value must be a hexadecimal number');
        }
        $this->hex = strtolower($v);
    }

    public function toString(): string { return $this->hex; }
    public function __toString(): string { return $this->hex; }
    public function jsonSerialize(): string { return $this->hex; }
}
