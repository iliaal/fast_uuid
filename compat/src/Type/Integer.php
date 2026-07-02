<?php

declare(strict_types=1);

namespace FastUuid\Compat\Type;

use FastUuid\Exception\InvalidArgumentException;

/** Mirrors Ramsey\Uuid\Type\Integer (arbitrary-precision decimal string). */
final class Integer implements \JsonSerializable, \Stringable
{
    private string $value;

    public function __construct(string|int $value)
    {
        $v = (string) $value;
        $neg = str_starts_with($v, '-');
        $digits = $neg ? substr($v, 1) : $v;
        if (!\preg_match('/^[0-9]+$/D', $digits)) {
            throw new InvalidArgumentException('Value must be a signed integer or a string containing only digits');
        }
        $digits = ltrim($digits, '0');
        $this->value = $digits === '' ? '0' : ($neg ? '-' : '') . $digits;
    }

    public function isNegative(): bool { return str_starts_with($this->value, '-'); }
    public function toString(): string { return $this->value; }
    public function __toString(): string { return $this->value; }
    public function jsonSerialize(): string { return $this->value; }
}
