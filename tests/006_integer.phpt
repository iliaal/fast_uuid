--TEST--
Uuid integer conversion: NIL, MAX, round-trip and numeric-string shape
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;

var_dump(Uuid::fromString(Uuid::NIL)->getInteger() === '0');

var_dump(Uuid::fromInteger('0')->equals(Uuid::fromString(Uuid::NIL)));

$u = Uuid::uuid4();
var_dump(Uuid::fromInteger($u->getInteger())->equals($u));

// MAX is 2^128 - 1.
var_dump(Uuid::fromString(Uuid::MAX)->getInteger() === '340282366920938463463374607431768211455');

var_dump(preg_match('/^\d+$/', $u->getInteger()) === 1);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
