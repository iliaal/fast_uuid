--TEST--
compat: Rfc4122\Fields constructor and getVersion() judge nil, max, variant and version in the documented order
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Rfc4122\Fields;
use FastUuid\Exception\InvalidArgumentException;

function message(string $bytes): string
{
    try {
        new Fields($bytes);
        return 'ok';
    } catch (InvalidArgumentException $e) {
        return $e->getMessage();
    }
}

$variant = 'The byte string does not conform to the RFC 9562 variant';
$version = 'The byte string does not contain a valid RFC 9562 version';

// Nil and max bypass both checks; everything else needs variant 10xx first.
var_dump(message(str_repeat("\0", 16)) === 'ok');
var_dump(message(str_repeat("\xff", 16)) === 'ok');
var_dump(message(hex2bin('00112233445546778899aabbccddeeff')) === 'ok');
var_dump(message(hex2bin('001122334455467700ffaabbccddeeff')) === $variant);
var_dump(message(hex2bin('001122334455467740ffaabbccddeeff')) === $variant);
var_dump(message(hex2bin('0011223344554677c0ffaabbccddeeff')) === $variant);
var_dump(message(hex2bin('0011223344554677e0ffaabbccddeeff')) === $variant);
// Variant check precedes the version check when both fail.
var_dump(message(hex2bin('001122334455007700ffaabbccddeeff')) === $variant);
var_dump(message(hex2bin('001122334455007780ffaabbccddeeff')) === $version);
var_dump(message(hex2bin('0011223344559077bfffaabbccddeeff')) === $version);
var_dump(message(hex2bin('0011223344550f77b0ffaabbccddeeff')) === $version);
var_dump(message(hex2bin('0011223344559077b0ffaabbccddeeff')) === $version);
var_dump(message(hex2bin('001122334455f077b0ffaabbccddeeff')) === $version);
for ($v = 1; $v <= 8; $v++) {
    var_dump(message(hex2bin('00112233445' . '5' . dechex($v) . '077' . '8899aabbccddeeff')) === 'ok');
}
var_dump(message('short') === 'Fields expects exactly 16 bytes, got 5');
var_dump(message('') === 'Fields expects exactly 16 bytes, got 0');

// getVersion() and its C counterpart agree on every byte-6 nibble and byte-8 variant.
$agree = true;
for ($nibble = 0; $nibble < 16; $nibble++) {
    foreach ([0x00, 0x7f, 0x80, 0xbf, 0xc0, 0xdf, 0xe0, 0xff] as $octet) {
        $bytes = "\x00\x11\x22\x33\x44\x55" . chr(($nibble << 4) | 0x07) . "\x77" . chr($octet) . "\x99\xaa\xbb\xcc\xdd\xee\xff";
        $core = \FastUuid\Uuid::fromBytes($bytes);
        try {
            $fields = new Fields($bytes);
            $agree = $agree && $fields->getVersion() === $core->getVersion() && $fields->getVariant() === $core->getVariant();
        } catch (InvalidArgumentException) {
            $agree = $agree && ((($octet & 0xc0) !== 0x80) || $nibble < 1 || $nibble > 8);
        }
    }
}
var_dump($agree);
var_dump((new Fields(str_repeat("\0", 16)))->getVersion() === null);
var_dump((new Fields(str_repeat("\xff", 16)))->getVersion() === null);
var_dump((new Fields(str_repeat("\0", 16)))->isNil() && (new Fields(str_repeat("\xff", 16)))->isMax());

$restored = unserialize(serialize(new Fields(hex2bin('00112233445546778899aabbccddeeff'))));
var_dump($restored->getVersion() === 4);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
