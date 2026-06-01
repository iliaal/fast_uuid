--TEST--
fast_uuid: tolerant parser round-trips, isValid, error path
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;

$u = Uuid::uuid4();
$s = (string)$u;

// fromString accepts the permissive forms
var_dump(Uuid::fromString($s)->equals($u));
var_dump(Uuid::fromString('urn:uuid:' . $s)->equals($u));
var_dump(Uuid::fromString('{' . $s . '}')->equals($u));
var_dump(Uuid::fromString(strtoupper($s))->equals($u));
var_dump(Uuid::fromString($u->getHex())->equals($u));

// raw-bytes and hex factories
var_dump(Uuid::fromBytes($u->getBytes())->equals($u));
var_dump(Uuid::fromHexadecimal($u->getHex())->equals($u));

// isValid
var_dump(Uuid::isValid($s) === true);
var_dump(Uuid::isValid('urn:uuid:' . $s) === true);
var_dump(Uuid::isValid('not-a-uuid') === false);
var_dump(Uuid::isValid('') === false);
var_dump(Uuid::isValid(substr($s, 0, 35)) === false);

// bad input throws
$threw = false;
try {
    Uuid::fromString('zzzz');
} catch (\FastUuid\Exception\InvalidUuidStringException $e) {
    $threw = true;
}
var_dump($threw);
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
