<?php

declare(strict_types=1);

namespace FastUuid\Compat\Nonstandard;

use FastUuid\Compat\Rfc4122\FieldsInterface;
use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Exception\InvalidArgumentException;

final class Fields implements FieldsInterface
{
    public function __construct(private string $bytes)
    {
        if (\strlen($bytes) !== 16) {
            throw new InvalidArgumentException(
                'Fields expects exactly 16 bytes, got ' . \strlen($bytes),
            );
        }
    }

    public function getBytes(): string { return $this->bytes; }

    private function slice(int $start, int $length): Hexadecimal
    {
        return new Hexadecimal(\bin2hex(\substr($this->bytes, $start, $length)));
    }

    public function getTimeLow(): Hexadecimal { return $this->slice(0, 4); }
    public function getTimeMid(): Hexadecimal { return $this->slice(4, 2); }
    public function getTimeHiAndVersion(): Hexadecimal { return $this->slice(6, 2); }
    public function getClockSeqHiAndReserved(): Hexadecimal { return $this->slice(8, 1); }
    public function getClockSeqLow(): Hexadecimal { return $this->slice(9, 1); }
    public function getNode(): Hexadecimal { return $this->slice(10, 6); }

    public function getClockSeq(): Hexadecimal
    {
        $clockSeq = ((\ord($this->bytes[8]) & 0x3f) << 8) | \ord($this->bytes[9]);
        return new Hexadecimal(\sprintf('%04x', $clockSeq));
    }

    public function getTimestamp(): Hexadecimal
    {
        return new Hexadecimal(
            \substr(\bin2hex(\substr($this->bytes, 6, 2)), 1)
            . \bin2hex(\substr($this->bytes, 4, 2))
            . \bin2hex(\substr($this->bytes, 0, 4)),
        );
    }

    public function getVersion(): ?int { return null; }

    public function getVariant(): int
    {
        $octet = \ord($this->bytes[8]);
        if (($octet & 0x80) === 0x00) return 0;
        if (($octet & 0xc0) === 0x80) return 2;
        if (($octet & 0xe0) === 0xc0) return 6;
        return 7;
    }

    public function isNil(): bool { return false; }
    public function isMax(): bool { return false; }
    public function serialize(): string { return $this->bytes; }
    public function __serialize(): array { return ['bytes' => $this->bytes]; }
    public function unserialize(string $data): void
    {
        $this->__construct(\strlen($data) === 16 ? $data : \base64_decode($data));
    }
    public function __unserialize(array $data): void
    {
        if (!isset($data['bytes'])) {
            throw new \ValueError(\sprintf('%s(): Argument #1 ($data) is invalid', __METHOD__));
        }
        $this->unserialize($data['bytes']);
    }
}
