--TEST--
Integer timestamp API: uuid_v7_at(), Uuid::uuid7(int), getTimestampMillis()
--EXTENSIONS--
fast_uuid
--SKIPIF--
<?php if (PHP_INT_SIZE < 8) die('skip 64-bit only: millisecond timestamps exceed 32-bit PHP_INT_MAX'); ?>
--FILE--
<?php
declare(strict_types=1);
use FastUuid\Uuid;
use FastUuid\Exception\InvalidArgumentException as IAE;
use FastUuid\Exception\UnsupportedOperationException as UOE;

function throws(callable $fn, string $c): bool { try { $fn(); return false; } catch (\Throwable $e) { return $e instanceof $c; } }

$ms = 1672576496123;

// procedural int generator round-trips exactly through the millis reader
$u = Uuid::fromString(uuid_v7_at($ms));
var_dump($u->getVersion() === 7);
var_dump($u->getTimestampMillis() === $ms);

// object int path agrees with the procedural one and with an equivalent DateTime
var_dump(Uuid::uuid7($ms)->getTimestampMillis() === $ms);
$dt = (new DateTimeImmutable('@0'))->modify("+$ms milliseconds");
var_dump(Uuid::uuid7($dt)->getTimestampMillis() === $ms);

// getTimestampMillis matches the DateTime-derived value for every time-based version
foreach ([Uuid::uuid1(), Uuid::uuid6(), Uuid::uuid7()] as $t) {
    var_dump($t->getTimestampMillis() === (int) $t->getDateTime()->format('Uv'));
}

// non-time-based versions have no timestamp to read
var_dump(throws(fn() => Uuid::uuid4()->getTimestampMillis(), UOE::class));

// millisecond range: 0 and the 48-bit ceiling are valid; out-of-range throws
var_dump(Uuid::fromString(uuid_v7_at(0))->getTimestampMillis() === 0);
var_dump(Uuid::fromString(uuid_v7_at(281474976710655))->getTimestampMillis() === 281474976710655);
var_dump(throws(fn() => uuid_v7_at(-1), IAE::class));
var_dump(throws(fn() => uuid_v7_at(281474976710656), IAE::class));

// wrong argument types are TypeErrors, like any typed parameter
var_dump(throws(fn() => Uuid::uuid7(new stdClass()), \TypeError::class));
var_dump(throws(fn() => Uuid::uuid7('2023-01-01'), \TypeError::class));

// null still auto-generates a monotonic v7
var_dump(Uuid::uuid7()->getVersion() === 7);
var_dump(Uuid::uuid7(null)->getVersion() === 7);
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
