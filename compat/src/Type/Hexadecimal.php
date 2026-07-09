<?php

declare(strict_types=1);

namespace FastUuid\Compat\Type;

use FastUuid\Exception\InvalidArgumentException;

/** Mirrors Ramsey\Uuid\Type\Hexadecimal. */
final class Hexadecimal implements \JsonSerializable, \Stringable
{
    private string $hex;

    public function __construct(string|\Stringable $value)
    {
        $v = (string) $value;
        // Accept a case-insensitive "0x"/"0X" prefix (ramsey parity).
        if (\strlen($v) >= 2 && $v[0] === '0' && ($v[1] === 'x' || $v[1] === 'X')) {
            $v = \substr($v, 2);
        }
        if (!\preg_match('/^[0-9a-fA-F]+$/D', $v)) {
            throw new InvalidArgumentException('Value must be a hexadecimal number');
        }
        $this->hex = strtolower($v);
    }

    public function toString(): string { return $this->hex; }
    public function __toString(): string { return $this->hex; }
    public function jsonSerialize(): string { return $this->hex; }
    public function __serialize(): array { return ['string' => $this->hex]; }
    public function __unserialize(array $data): void
    {
        // Revalidate through the constructor (ramsey parity): a tampered
        // payload must not produce an invalid value object.
        $this->hex = (new self((string) ($data['string'] ?? '')))->hex;
    }
}
