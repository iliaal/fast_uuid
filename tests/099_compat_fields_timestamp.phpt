--TEST--
compat: Fields timestamp layouts retain all 60 bits and reflect restoration
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Rfc4122\Fields;
use FastUuid\Compat\Nonstandard\Fields as NonstandardFields;
use FastUuid\Compat\Type\Hexadecimal;

foreach ([
    ['aabbccddeeff11998b1c2d3e4f506172', '199eeffaabbccdd'],
    ['aabbccddeeff21998b1c2d3e4f506172', '199eeff00000000'],
    ['aabbccddeeff31998b1c2d3e4f506172', '199eeffaabbccdd'],
    ['aabbccddeeff41998b1c2d3e4f506172', '199eeffaabbccdd'],
    ['aabbccddeeff51998b1c2d3e4f506172', '199eeffaabbccdd'],
    ['aabbccddeeff61998b1c2d3e4f506172', 'aabbccddeeff199'],
    ['aabbccddeeff71998b1c2d3e4f506172', '000aabbccddeeff'],
    ['aabbccddeeff81998b1c2d3e4f506172', '199eeffaabbccdd'],
    [str_repeat('0', 32), str_repeat('0', 15)],
    [str_repeat('f', 32), str_repeat('f', 15)],
    ['ffffffffffff1fffbfffffffffffffff', str_repeat('f', 15)],
] as [$hex, $expected]) {
    $fields = new Fields(hex2bin($hex));
    $timestamp = $fields->getTimestamp();
    var_dump($timestamp instanceof Hexadecimal && (string) $timestamp === $expected);
    var_dump((string) $fields->getTimestamp() === $expected);
}

foreach ([Fields::class, NonstandardFields::class] as $class) {
    $fields = new $class(hex2bin('aabbccddeeff11998b1c2d3e4f506172'));
    $previous = $fields->getTimestamp();
    $fields->__construct(hex2bin('0123456789ab1cde8f0123456789abcd'));
    var_dump((string) $fields->getTimestamp() === 'cde89ab01234567');
    var_dump((string) $previous === '199eeffaabbccdd');
    $fields->__unserialize(['bytes' => hex2bin('fedcba98765413218000000000000000')]);
    var_dump((string) $fields->getTimestamp() === '3217654fedcba98');
}

// Nonstandard fields always use the original field order, regardless of version.
foreach ([0, 2, 6, 7, 15] as $version) {
    $fields = new NonstandardFields(hex2bin('aabbccddeeff' . dechex($version) . '199cb1c2d3e4f506172'));
    var_dump((string) $fields->getTimestamp() === '199eeffaabbccdd');
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
