--TEST--
fromDateTime honors clockSeq, pre-1970 round-trips, DateTimeInterface enforced
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;

$dt = new DateTimeImmutable('2020-01-02 03:04:05');

// explicit clock sequence is applied (variant bits + value) and deterministic
$a = Uuid::fromDateTime($dt, null, 0x1234)->getBytes();
$b = Uuid::fromDateTime($dt, null, 0x1234)->getBytes();
var_dump((ord($a[8]) & 0x3f) === 0x12 && ord($a[9]) === 0x34);
var_dump(substr($a, 8, 2) === substr($b, 8, 2));

// pre-1970 dates round-trip (signed timestamp math)
$old = new DateTimeImmutable('1960-01-02 03:04:05.123456');
$w = Uuid::fromDateTime($old);
var_dump($w->getVersion() === 1);
var_dump($w->getDateTime()->format('Y-m-d H:i:s') === '1960-01-02 03:04:05');

// uuid7 from an explicit DateTime
$u7 = Uuid::uuid7($dt);
var_dump($u7->getVersion() === 7);
var_dump($u7->getDateTime()->getTimestamp() === $dt->getTimestamp());

// the declared DateTimeInterface type is enforced
try { Uuid::uuid7(new stdClass()); var_dump(false); } catch (\TypeError $e) { var_dump(true); }
try { Uuid::fromDateTime(new stdClass()); var_dump(false); } catch (\TypeError $e) { var_dump(true); }
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
