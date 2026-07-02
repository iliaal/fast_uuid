--TEST--
getVariant Microsoft/future branches; fromInteger and parser rejection paths
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;
use FastUuid\Exception\InvalidArgumentException;
use FastUuid\Exception\InvalidUuidStringException;

// Variant decode across all four families (byte 8 high bits).
var_dump(Uuid::fromString('6ba7b810-9dad-11d1-00b4-00c04fd430c8')->getVariant() === 0); // NCS
var_dump(Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8')->getVariant() === 2); // RFC
var_dump(Uuid::fromString('6ba7b810-9dad-11d1-c0b4-00c04fd430c8')->getVariant() === 6); // Microsoft
var_dump(Uuid::fromString('6ba7b810-9dad-11d1-e0b4-00c04fd430c8')->getVariant() === 7); // future
// Non-RFC variants report no version.
var_dump(Uuid::fromString('6ba7b810-9dad-11d1-c0b4-00c04fd430c8')->getVersion() === null);
var_dump(Uuid::fromString('6ba7b810-9dad-11d1-e0b4-00c04fd430c8')->getVersion() === null);

// fromInteger rejects out-of-range and non-numeric input.
$max = Uuid::fromString(Uuid::MAX);
var_dump(Uuid::fromInteger($max->getInteger())->equals($max)); // 2^128-1 still accepted
foreach ([
    '340282366920938463463374607431768211456', // 2^128
    '999999999999999999999999999999999999999',
    '12x',
    '',
    '-1',
] as $bad) {
    $threw = false;
    try { Uuid::fromInteger($bad); } catch (InvalidArgumentException) { $threw = true; }
    var_dump($threw);
}

// Parser: right length, bad content — every accepted form rejects a non-hex
// digit at a valid position, and an embedded NUL never parses.
foreach ([
    "6ba7b810-9dad-11d1-80b4-00c04fd430cg",   // canonical, 'g'
    "6ba7b8109dad11d180b400c04fd430cg",       // bare 32-hex, 'g'
    "{6ba7b810-9dad-11d1-80b4-00c04fd430cg}", // braced, 'g'
    "urn:uuid:6ba7b810-9dad-11d1-80b4-00c04fd430cg",
    "6ba7b810-9dad-11d1-80b4-00c04fd430c\0",  // embedded NUL, length 36
    "6ba7b810-9dad-11d1-80b4\0-00c04fd430c8", // NUL mid-string, length 37
] as $bad) {
    $threw = false;
    try { Uuid::fromString($bad); } catch (InvalidUuidStringException) { $threw = true; }
    var_dump($threw && !uuid_is_valid($bad));
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
