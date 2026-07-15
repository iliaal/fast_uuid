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
        if (!$this->isNil() && !$this->isMax() && $this->getVariant() !== 2) {
            throw new \FastUuid\Exception\InvalidArgumentException(
                'The byte string does not conform to the RFC 9562 variant',
            );
        }
        $version = $this->getVersion();
        if (!$this->isNil() && !$this->isMax() && ($version === null || $version < 1 || $version > 8)) {
            throw new \FastUuid\Exception\InvalidArgumentException(
                'The byte string does not contain a valid RFC 9562 version',
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
        if ($this->isMax()) {
            // ramsey parity: the max UUID reports the unmasked ffff
            return new Hexadecimal('ffff');
        }
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
        // Non-RFC variants carry no version field. Keep in sync with the C
        // getVersion (fast_uuid.c, variant guard on byte 8).
        if ($this->getVariant() !== 2) {
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
            // v7: 48-bit unix_ts_ms, zero-padded to 15 nibbles (ramsey returns
            // a 60-bit value for consistency across versions).
            return new Hexadecimal('000' . bin2hex(substr($b, 0, 6)));
        }
        if ($v === 2) {
            // v2 (DCE): time_low carries the local identifier, so only the
            // upper 28 timestamp bits survive. Zero the low 32 like ramsey
            // and the C decoder (fu_decode_time).
            $timeMid = bin2hex(substr($b, 4, 2));
            $timeHi  = substr(bin2hex(substr($b, 6, 2)), 1);
            return new Hexadecimal($timeHi . $timeMid . '00000000');
        }
        $timeLow = bin2hex(substr($b, 0, 4));
        $timeMid = bin2hex(substr($b, 4, 2));
        $timeHi  = substr(bin2hex(substr($b, 6, 2)), 1);
        return new Hexadecimal($timeHi . $timeMid . $timeLow);
    }

    public function isNil(): bool { return $this->bytes === self::NIL_BYTES; }
    public function isMax(): bool { return $this->bytes === self::MAX_BYTES; }
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
