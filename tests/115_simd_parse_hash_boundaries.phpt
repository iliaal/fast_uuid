--TEST--
SIMD hex parsing, inline MD5/SHA-1 block boundaries, and v7 batch refills match reference semantics
--EXTENSIONS--
fast_uuid
--FILE--
<?php
declare(strict_types=1);

require __DIR__ . '/_autoload.inc';

// 1. Exhaustive single-byte check across 0x00..0xFF for canonical 36-char and bare 32-char inputs.
$base36 = '01234567-89ab-4def-8123-456789abcdef';
$base32 = '0123456789ab4def8123456789abcdef';
$positions36 = [0, 7, 9, 12, 14, 15, 16, 17, 19, 22, 24, 35];
$positions32 = [0, 15, 16, 31];
$parseOk = true;
for ($b = 0; $b < 256; $b++) {
    $ch = chr($b);
    $isHex = ($b >= 0x30 && $b <= 0x39) || ($b >= 0x41 && $b <= 0x46) || ($b >= 0x61 && $b <= 0x66);
    foreach ($positions36 as $pos) {
        $s = $base36;
        $s[$pos] = $ch;
        $validTol = uuid_is_valid($s);
        $validStr = uuid_is_valid($s, true);
        if ($validTol !== $isHex || $validStr !== $isHex) {
            $parseOk = false;
            echo "36 valid mismatch byte=$b pos=$pos\n";
            break 2;
        }
        if ($isHex) {
            $expected = hex2bin(str_replace('-', '', strtolower($s)));
            if (uuid_to_bin($s) !== $expected || uuid_from_bin($expected) !== strtolower($s)) {
                $parseOk = false;
                echo "36 roundtrip mismatch byte=$b pos=$pos\n";
                break 2;
            }
        } else {
            try {
                uuid_to_bin($s);
                $parseOk = false;
                echo "36 accepted invalid byte=$b pos=$pos\n";
                break 2;
            } catch (\FastUuid\Exception\InvalidUuidStringException) {
            }
        }
    }
    foreach ($positions32 as $pos) {
        $s = $base32;
        $s[$pos] = $ch;
        if (uuid_is_valid($s) !== $isHex || uuid_is_valid($s, true) !== false) {
            $parseOk = false;
            echo "32 valid mismatch byte=$b pos=$pos\n";
            break 2;
        }
        if ($isHex) {
            $expected = hex2bin(strtolower($s));
            if (uuid_to_bin($s) !== $expected) {
                $parseOk = false;
                echo "32 decode mismatch byte=$b pos=$pos\n";
                break 2;
            }
        }
    }
}
// Every hyphen slot must reject non-hyphen characters.
foreach ([8, 13, 18, 23] as $hpos) {
    $s = $base36;
    $s[$hpos] = '0';
    if (uuid_is_valid($s) || uuid_is_valid($s, true)) {
        $parseOk = false;
        echo "hyphen slot $hpos accepted non-hyphen\n";
    }
}
var_dump($parseOk);

// 2. v3 (MD5) and v5 (SHA-1) across single-block and multi-block padding boundaries.
$nsStr = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
$nsBin = uuid_to_bin($nsStr);
$hashOk = true;
foreach ([0, 1, 15, 38, 39, 40, 41, 47, 48, 49, 55, 56, 57, 63, 64, 65, 100, 111, 112, 128, 1024] as $len) {
    $name = str_repeat('Ab09!x', intdiv($len, 6) + 1);
    $name = substr($name, 0, $len);

    $md5 = md5($nsBin . $name, true);
    $md5[6] = chr((ord($md5[6]) & 0x0f) | 0x30);
    $md5[8] = chr((ord($md5[8]) & 0x3f) | 0x80);
    if (uuid_v3_bin($nsStr, $name) !== $md5 || uuid_v3($nsStr, $name) !== uuid_from_bin($md5)) {
        $hashOk = false;
        echo "v3 mismatch at len=$len\n";
        break;
    }

    $sha1 = substr(sha1($nsBin . $name, true), 0, 16);
    $sha1[6] = chr((ord($sha1[6]) & 0x0f) | 0x50);
    $sha1[8] = chr((ord($sha1[8]) & 0x3f) | 0x80);
    if (uuid_v5_bin($nsStr, $name) !== $sha1 || uuid_v5($nsStr, $name) !== uuid_from_bin($sha1)) {
        $hashOk = false;
        echo "v5 mismatch at len=$len\n";
        break;
    }
}
var_dump($hashOk);

// 3. v7 batch monotonicity and formatting across multiple 8 KiB CSPRNG refills (> 2730 items).
$v7s = uuid_v7_batch(4000);
$v7b = uuid_v7_bin_batch(4000);
$v7Ok = count($v7s) === 4000 && count($v7b) === 4000;
for ($i = 0; $i < 4000 && $v7Ok; $i++) {
    if (!uuid_is_valid($v7s[$i], true) || strlen($v7b[$i]) !== 16) {
        $v7Ok = false;
        break;
    }
    $rawS = uuid_to_bin($v7s[$i]);
    if (((ord($rawS[6]) >> 4) & 0x0f) !== 7 || (ord($rawS[8]) & 0xc0) !== 0x80) {
        $v7Ok = false;
        break;
    }
    if (((ord($v7b[$i][6]) >> 4) & 0x0f) !== 7 || (ord($v7b[$i][8]) & 0xc0) !== 0x80) {
        $v7Ok = false;
        break;
    }
    if ($i > 0 && ($v7s[$i] <= $v7s[$i - 1] || $v7b[$i] <= $v7b[$i - 1])) {
        $v7Ok = false;
        break;
    }
}
// Cross-batch ordering: bin batch ran strictly after string batch.
$v7Ok = $v7Ok && uuid_from_bin($v7b[0]) > $v7s[3999];
var_dump($v7Ok);

// 4. UuidFactory subclass overriding wrap() is honoured by fromHexadecimal().
$wrapCalls = 0;
$factory = new class($wrapCalls) extends \FastUuid\Compat\UuidFactory {
    private int $calls;
    public function __construct(int &$calls) {
        $this->calls = &$calls;
    }
    public function wrap(\FastUuid\Uuid $core, ?\FastUuid\Compat\Codec\CodecInterface $codec = null): \FastUuid\Compat\UuidInterface {
        $this->calls++;
        return parent::wrap($core, $codec);
    }
};
$hexU = $factory->fromHexadecimal('0123456789ab4def8123456789abcdef');
var_dump($wrapCalls === 1, $hexU->toString() === '01234567-89ab-4def-8123-456789abcdef');
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
