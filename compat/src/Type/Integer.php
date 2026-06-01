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
        if ($digits === '' || !ctype_digit($digits)) {
            throw new InvalidArgumentException('Value must be a signed integer or a string containing only digits');
        }
        $this->value = ($neg ? '-' : '') . ltrim($digits, '0') ?: '0';
    }

    public function isNegative(): bool { return str_starts_with($this->value, '-'); }
    public function toString(): string { return $this->value; }
    public function __toString(): string { return $this->value; }
    public function jsonSerialize(): string { return $this->value; }
}
