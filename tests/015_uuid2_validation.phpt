--TEST--
uuid2 validates the local identifier (range + numeric string)
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;

// valid: decimal string, int, and the uint32 maximum
var_dump(unpack('N', substr(Uuid::uuid2(Uuid::DCE_DOMAIN_PERSON, '4242')->getBytes(), 0, 4))[1] === 4242);
var_dump(unpack('N', substr(Uuid::uuid2(Uuid::DCE_DOMAIN_PERSON, 4242)->getBytes(), 0, 4))[1] === 4242);
var_dump(unpack('N', substr(Uuid::uuid2(0, '4294967295')->getBytes(), 0, 4))[1] === 4294967295);

// invalid strings, overflow, and negatives are rejected (not silently coerced)
foreach (['abc', '12x', '4294967296', '99999999999', '-5', ''] as $bad) {
    try { Uuid::uuid2(0, $bad); var_dump(false); }
    catch (\FastUuid\Exception\InvalidArgumentException $e) { var_dump(true); }
}
foreach ([-1, 0x1_0000_0000] as $bad) {
    try { Uuid::uuid2(0, $bad); var_dump(false); }
    catch (\FastUuid\Exception\InvalidArgumentException $e) { var_dump(true); }
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
