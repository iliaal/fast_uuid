--TEST--
Input validation: timestamp range (v1/v7), node/clockSeq bounds, uuid2 domain, fromHexadecimal Stringable, userland namespace
--EXTENSIONS--
fast_uuid
--FILE--
<?php
declare(strict_types=1);
use FastUuid\Uuid;
use FastUuid\Exception\InvalidArgumentException as IAE;

function throws(callable $fn, string $class = IAE::class): bool {
    try { $fn(); return false; } catch (\Throwable $e) { return $e instanceof $class; }
}
$utc = new DateTimeZone('UTC');

// --- CR-002: v7 timestamp range -------------------------------------------
$overV7 = new DateTimeImmutable('@' . (intdiv(1 << 48, 1000) + 1)); // just past the 48-bit ms ceiling
var_dump(throws(fn() => Uuid::uuid7($overV7)));                     // over-range throws
var_dump(throws(fn() => Uuid::uuid7(new DateTimeImmutable('@-1')))); // pre-1970 throws
var_dump(Uuid::uuid7(new DateTimeImmutable('2023-01-01 00:00:00.5', $utc))->getVersion() === 7); // valid ok

// --- CR-002: v1 fromDateTime range ----------------------------------------
var_dump(throws(fn() => Uuid::fromDateTime(new DateTimeImmutable('6000-01-01', $utc)))); // > ~year 5236 throws
var_dump(throws(fn() => Uuid::fromDateTime(new DateTimeImmutable('1500-01-01', $utc)))); // < 1582 throws
var_dump(Uuid::fromDateTime(new DateTimeImmutable('1969-12-31 23:59:59', $utc))->getVersion() === 1); // pre-1970 still valid
var_dump(Uuid::fromDateTime(new DateTimeImmutable('2020-06-15 12:00:00.5', $utc))->getVersion() === 1);

// --- CR-003: node / clockSeq bounds ---------------------------------------
var_dump(throws(fn() => Uuid::uuid1(-1)));               // negative node rejected
// 48-bit node literals exceed PHP_INT_MAX on 32-bit PHP (they parse as float),
// so test the numeric-overflow rejection and max node via int on 64-bit and
// via string on 32-bit.
if (PHP_INT_SIZE >= 8) {
    var_dump(throws(fn() => Uuid::uuid1(2 ** 48)));          // > 48-bit node rejected
    var_dump(substr(Uuid::uuid1(0xffffffffffff)->getHex(), 20) === 'ffffffffffff'); // max node ok, not truncated
} else {
    var_dump(throws(fn() => Uuid::uuid1('zzzzzzzzzzzz')));   // non-hex node rejected
    var_dump(substr(Uuid::uuid1('ffffffffffff')->getHex(), 20) === 'ffffffffffff'); // max node via string, not truncated
}
var_dump(throws(fn() => Uuid::uuid1('0123456789ab', 0x4000))); // clockSeq > 14 bits rejected
var_dump(Uuid::uuid1('0123456789ab', 0x3fff)->getVersion() === 1); // max clockSeq ok

// --- CR-004: uuid2 domain handling ----------------------------------------
var_dump(throws(fn() => Uuid::uuid2(2)));                 // ORG without localIdentifier rejected
var_dump(Uuid::uuid2(2, 100)->getVersion() === 2);       // ORG with explicit id ok
if (PHP_OS_FAMILY === 'Windows') {
    // No POSIX uid/gid to auto-fill from: PERSON/GROUP without an id throw.
    var_dump(throws(fn() => Uuid::uuid2(0)));             // PERSON: no auto-fill on Windows
    var_dump(throws(fn() => Uuid::uuid2(1)));             // GROUP: no auto-fill on Windows
} else {
    var_dump(Uuid::uuid2(0)->getVersion() === 2);        // PERSON auto-fills
    var_dump(Uuid::uuid2(1)->getVersion() === 2);        // GROUP auto-fills
}

// --- CR-005: fromHexadecimal Stringable contract --------------------------
$thrower = new class { public function __toString(): string { throw new \RuntimeException('boom'); } };
var_dump(throws(fn() => Uuid::fromHexadecimal($thrower), \RuntimeException::class)); // original error propagates
var_dump(throws(fn() => Uuid::fromHexadecimal(new stdClass())));                     // non-Stringable rejected
$hexObj = new class implements \Stringable { public function __toString(): string { return '0a1b2c3d4e5f60718293a4b5c6d7e8f9'; } };
var_dump(Uuid::fromHexadecimal($hexObj)->getHex() === '0a1b2c3d4e5f60718293a4b5c6d7e8f9');

// --- CR-006: userland UuidInterface as a uuid3/5 namespace ----------------
$ns = new class implements \FastUuid\UuidInterface {
    public function __toString(): string { return '6ba7b810-9dad-11d1-80b4-00c04fd430c8'; }
    public function jsonSerialize(): mixed { return (string) $this; }
};
var_dump(Uuid::uuid5($ns, 'x')->getVersion() === 5);
var_dump(Uuid::uuid3($ns, 'x')->getVersion() === 3);
// equivalence with the string namespace
var_dump(Uuid::uuid5($ns, 'x')->equals(Uuid::uuid5('6ba7b810-9dad-11d1-80b4-00c04fd430c8', 'x')));
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
