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

// composed wrappers: {urn:uuid:...} and urn:uuid:{...} (fu-cus)
var_dump(Uuid::isValid('{urn:uuid:6ba7b810-9dad-11d1-80b4-00c04fd430c8}'));
var_dump(Uuid::fromString('{urn:uuid:6ba7b810-9dad-11d1-80b4-00c04fd430c8}')->toString() === '6ba7b810-9dad-11d1-80b4-00c04fd430c8');
var_dump(Uuid::fromString('urn:uuid:{6BA7B810-9DAD-11D1-80B4-00C04FD430C8}')->toString() === '6ba7b810-9dad-11d1-80b4-00c04fd430c8');

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
bool(true)
bool(true)
bool(true)
