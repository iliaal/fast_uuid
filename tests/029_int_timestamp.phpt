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

$u = Uuid::fromString(uuid_v7_at($ms));
var_dump($u->getVersion() === 7);
var_dump($u->getTimestampMillis() === $ms);

var_dump(Uuid::uuid7($ms)->getTimestampMillis() === $ms);
$dt = (new DateTimeImmutable('@0'))->modify("+$ms milliseconds");
var_dump(Uuid::uuid7($dt)->getTimestampMillis() === $ms);

// v1/v6 carry 100 ns ticks. The integer API floors milliseconds, while
// getDateTime() rounds to microseconds and can cross a millisecond boundary.
foreach ([
    'below' => ['b29d9caa-89d0-11ed-8000-010203040506', '1ed89d0b-29d9-6caa-8000-010203040506'],
    'half' => ['b29d9cab-89d0-11ed-8000-010203040506', '1ed89d0b-29d9-6cab-8000-010203040506'],
    'exact' => ['b29d9cb0-89d0-11ed-8000-010203040506', '1ed89d0b-29d9-6cb0-8000-010203040506'],
    'rollover' => ['b3236e7b-89d0-11ed-8000-010203040506', '1ed89d0b-3236-6e7b-8000-010203040506'],
] as $case => $strings) {
    foreach ($strings as $string) {
        $t = Uuid::fromString($string);
        printf("%s v%d: %s %s\n", $case, $t->getVersion(),
            var_export($t->getTimestampMillis(), true), var_export($t->getDateTime()->format('U.u'), true));
    }
}
$liveV7 = Uuid::uuid7();
var_dump($liveV7->getTimestampMillis() === (int) $liveV7->getDateTime()->format('Uv'));

var_dump(throws(fn() => Uuid::uuid4()->getTimestampMillis(), UOE::class));

var_dump(Uuid::fromString(uuid_v7_at(0))->getTimestampMillis() === 0);
var_dump(Uuid::fromString(uuid_v7_at(281474976710655))->getTimestampMillis() === 281474976710655);
var_dump(throws(fn() => uuid_v7_at(-1), IAE::class));
var_dump(throws(fn() => uuid_v7_at(281474976710656), IAE::class));

var_dump(throws(fn() => Uuid::uuid7(new stdClass()), \TypeError::class));
var_dump(throws(fn() => Uuid::uuid7('2023-01-01'), \TypeError::class));

var_dump(Uuid::uuid7()->getVersion() === 7);
var_dump(Uuid::uuid7(null)->getVersion() === 7);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
below v1: 1672576496122 '1672576496.122999'
below v6: 1672576496122 '1672576496.122999'
half v1: 1672576496122 '1672576496.123000'
half v6: 1672576496122 '1672576496.123000'
exact v1: 1672576496123 '1672576496.123000'
exact v6: 1672576496123 '1672576496.123000'
rollover v1: 1672576496999 '1672576497.000000'
rollover v6: 1672576496999 '1672576497.000000'
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
