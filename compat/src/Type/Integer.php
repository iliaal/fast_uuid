<?php

declare(strict_types=1);

namespace FastUuid\Compat\Type;

use FastUuid\Exception\InvalidArgumentException;

/** Mirrors Ramsey\Uuid\Type\Integer (arbitrary-precision decimal string). */
final class Integer implements \JsonSerializable, \Stringable
{
    private string $value;

    public function __construct(string|int|float|\Stringable $value)
    {
        if (\is_float($value)) {
            // Only whole, finite floats map to an integer (42.0 -> "42").
            if (!\is_finite($value) || \floor($value) !== $value) {
                throw new InvalidArgumentException('Value must be a signed integer or a string containing only digits');
            }
            $v = \sprintf('%.0f', $value);
        } else {
            $v = (string) $value;
        }
        $neg = false;
        if ($v !== '' && ($v[0] === '+' || $v[0] === '-')) {
            $neg = $v[0] === '-';
            $v = \substr($v, 1);
        }
        if (!\preg_match('/^[0-9]+$/D', $v)) {
            throw new InvalidArgumentException('Value must be a signed integer or a string containing only digits');
        }
        $digits = ltrim($v, '0');
        $this->value = $digits === '' ? '0' : ($neg ? '-' : '') . $digits;
    }

    public function isNegative(): bool { return str_starts_with($this->value, '-'); }
    public function toString(): string { return $this->value; }
    public function __toString(): string { return $this->value; }
    public function jsonSerialize(): string { return $this->value; }
    public function __serialize(): array { return ['string' => $this->value]; }
    public function __unserialize(array $data): void
    {
        // Revalidate through the constructor (ramsey parity).
        $this->value = (new self((string) ($data['string'] ?? '')))->value;
    }
}
