--TEST--
Uuid::getDateTime() for time-based versions and rejection for v4
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;
use FastUuid\Exception\UnsupportedOperationException;

// Time-based versions yield a DateTimeImmutable.
var_dump(Uuid::uuid1()->getDateTime() instanceof \DateTimeImmutable);
var_dump(Uuid::uuid6()->getDateTime() instanceof \DateTimeImmutable);
var_dump(Uuid::uuid7()->getDateTime() instanceof \DateTimeImmutable);

// v7 carries ms precision; a UUID built from a known DateTime round-trips
// to the same integer second.
$dt = new \DateTimeImmutable('2020-01-02 03:04:05');
$u = Uuid::uuid7($dt);
var_dump($u->getDateTime()->getTimestamp() === $dt->getTimestamp());

// v4 has no timestamp; getDateTime() must throw.
$threw = false;
try {
    Uuid::uuid4()->getDateTime();
} catch (UnsupportedOperationException $e) {
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
