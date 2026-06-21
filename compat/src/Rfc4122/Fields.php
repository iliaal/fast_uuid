<?php

declare(strict_types=1);

namespace FastUuid\Compat\Rfc4122;

use FastUuid\Compat\Type\Hexadecimal;

final class Fields implements FieldsInterface
{
    private const NIL_BYTES = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
    private const MAX_BYTES = "\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff";

    /** @param string $bytes 16 raw bytes */
    public function __construct(private string $bytes)
    {
        if (\strlen($bytes) !== 16) {
            throw new \FastUuid\Exception\InvalidArgumentException(
                'Fields expects exactly 16 bytes, got ' . \strlen($bytes),
            );
        }
    }

    public function getBytes(): string { return $this->bytes; }

    private function slice(int $start, int $len): Hexadecimal
    {
        return new Hexadecimal(bin2hex(substr($this->bytes, $start, $len)));
    }

    public function getTimeLow(): Hexadecimal { return $this->slice(0, 4); }
    public function getTimeMid(): Hexadecimal { return $this->slice(4, 2); }
    public function getTimeHiAndVersion(): Hexadecimal { return $this->slice(6, 2); }
    public function getClockSeqHiAndReserved(): Hexadecimal { return $this->slice(8, 1); }
    public function getClockSeqLow(): Hexadecimal { return $this->slice(9, 1); }
    public function getNode(): Hexadecimal { return $this->slice(10, 6); }

    public function getClockSeq(): Hexadecimal
    {
        $hi = \ord($this->bytes[8]);
        // variant bits masked off per RFC layout
        $clockSeq = (($hi & 0x3f) << 8) | \ord($this->bytes[9]);
        return new Hexadecimal(sprintf('%04x', $clockSeq));
    }

    public function getVersion(): ?int
    {
        if ($this->isNil() || $this->isMax()) {
            return null;
        }
        return (\ord($this->bytes[6]) >> 4) & 0x0f;
    }

    public function getVariant(): int
    {
        // RFC 4122 variant detection (returns ramsey Uuid::RFC_4122 == 2 for the common case)
        $octet = \ord($this->bytes[8]);
        if (($octet & 0x80) === 0x00) return 0;        // NCS
        if (($octet & 0xc0) === 0x80) return 2;        // RFC 4122
        if (($octet & 0xe0) === 0xc0) return 6;        // Microsoft
        return 7;                                       // future
    }

    public function getTimestamp(): Hexadecimal
    {
        $b = $this->bytes;
        $v = $this->getVersion();
        // Reassemble as a hex string rather than hexdec()+shifts: the 60-bit
        // value exceeds PHP_INT_MAX on 32-bit PHP, where hexdec() returns float
        // and the bit-ops silently truncate. String slicing stays exact.
        if ($v === 6) {
            // v6: most-significant first across bytes 0..7, version nibble at
            // hex offset 12 — concat the 48 high bits with the 12 low bits.
            $hex = bin2hex(substr($b, 0, 8));
            return new Hexadecimal(substr($hex, 0, 12) . substr($hex, 13, 3));
        }
        if ($v === 7) {
            // v7: 48-bit unix_ts_ms
            return new Hexadecimal(bin2hex(substr($b, 0, 6)));
        }
        // v1 (default): timeHi(12 bits) . timeMid(16) . timeLow(32) = 15 nibbles.
        $timeLow = bin2hex(substr($b, 0, 4));            // 8 hex
        $timeMid = bin2hex(substr($b, 4, 2));            // 4 hex
        $timeHi  = substr(bin2hex(substr($b, 6, 2)), 1); // drop version nibble -> 3 hex
        return new Hexadecimal($timeHi . $timeMid . $timeLow);
    }

    public function isNil(): bool { return $this->bytes === self::NIL_BYTES; }
    public function isMax(): bool { return $this->bytes === self::MAX_BYTES; }
}
