--TEST--
fromHexadecimal malformed-form table (CR-027)
--EXTENSIONS--
fast_uuid
--FILE--
<?php
declare(strict_types=1);
use FastUuid\Exception\InvalidUuidStringException;
use FastUuid\Uuid;

function hexRejects(string $hex): bool {
    try {
        Uuid::fromHexadecimal($hex);
        return false;
    } catch (InvalidUuidStringException) {
        return true;
    }
}

$hex = '0a1b2c3d4e5f60718293a4b5c6d7e8f9';

// Uppercase hex is valid input, normalized to lowercase.
var_dump(Uuid::fromHexadecimal(strtoupper($hex))->getHex() === $hex);
var_dump(Uuid::fromHexadecimal($hex)->getHex() === $hex);

// Every other malformed shape throws InvalidUuidStringException.
foreach ([
    'xyz',
    '0x' . $hex,
    substr($hex, 0, 31),
    $hex . 'a',
    ' ' . $hex,
    $hex . ' ',
    substr($hex, 0, 16) . ' ' . substr($hex, 17),
    "\0" . substr($hex, 1),
    'zz' . substr($hex, 2),
    '',
] as $bad) {
    var_dump(hexRejects($bad));
}
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
