--TEST--
Uuid::fromHexadecimal() with $pad accepts 1 to 32 hex digits, zero-filled on the left
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Exception\InvalidUuidStringException;
use FastUuid\Uuid;

var_dump(Uuid::fromHexadecimal('1', true)->getHex() === str_repeat('0', 31) . '1');
var_dump(Uuid::fromHexadecimal('ABCdef', true)->getHex() === str_repeat('0', 26) . 'abcdef');
var_dump(Uuid::fromHexadecimal('ff', true)->getInteger() === '255');
var_dump(Uuid::fromHexadecimal(str_repeat('f', 32), true)->getInteger() === '340282366920938463463374607431768211455');
var_dump(Uuid::fromHexadecimal('0000000a', true)->getInteger() === '10');
var_dump(Uuid::fromHexadecimal(new class { public function __toString(): string { return '2a'; } }, true)->getInteger() === '42');

$rejected = ['', str_repeat('f', 33), 'g', '-1', '0x1f', ' 1', '1 ', '{1}', 'urn:1', 'abc-def', "1\0"];
$ok = true;
foreach ($rejected as $hex) {
    try {
        Uuid::fromHexadecimal($hex, true);
        $ok = false;
        echo 'accepted ', var_export($hex, true), "\n";
    } catch (InvalidUuidStringException $e) {
        $ok = $ok && $e->getMessage() === 'Invalid hexadecimal UUID (expect 1 to 32 hex chars)';
    }
}
var_dump($ok);

// Without $pad only 32 digits are accepted, as before.
try {
    Uuid::fromHexadecimal('1');
    var_dump(false);
} catch (InvalidUuidStringException $e) {
    var_dump($e->getMessage() === 'Invalid hexadecimal UUID (expect 32 hex chars)');
}
var_dump(Uuid::fromHexadecimal(str_repeat('0', 31) . '1', false)->getInteger() === '1');
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
