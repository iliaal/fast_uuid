--TEST--
uuid7 embeds sub-ms precision in rand_a (RFC 9562 Method 3), stays monotonic; to* aliases
--EXTENSIONS--
fast_uuid
--SKIPIF--
<?php if (PHP_INT_SIZE < 8) die('skip 64-bit only: 48-bit millisecond field exceeds 32-bit PHP_INT_MAX'); ?>
--FILE--
<?php
declare(strict_types=1);
use FastUuid\Uuid;

function ms_of(string $b): int { $m = 0; for ($i = 0; $i < 6; $i++) $m = $m * 256 + ord($b[$i]); return $m; }
function rand_a(string $b): int { return ((ord($b[6]) & 0x0f) << 8) | ord($b[7]); }

// explicit path is now microsecond-precise (was second-precision only).
$dt  = new DateTimeImmutable('2023-01-01 12:34:56.123456', new DateTimeZone('UTC'));
$b   = Uuid::uuid7($dt)->getBytes();
$sec = $dt->getTimestamp();
var_dump(ms_of($b) === $sec * 1000 + 123);            // 48-bit field carries the ms
var_dump(rand_a($b) === intdiv(456 * 4096, 1000));    // rand_a = floor(frac_ms * 4096) = 1867

// two timestamps 1ms apart now differ in the ms field (would have been equal at sec precision).
$a = Uuid::uuid7(new DateTimeImmutable('2023-01-01 00:00:00.000000', new DateTimeZone('UTC')))->getBytes();
$c = Uuid::uuid7(new DateTimeImmutable('2023-01-01 00:00:00.001000', new DateTimeZone('UTC')))->getBytes();
var_dump(ms_of($c) - ms_of($a) === 1);

// auto path: strict monotonicity over a tight loop (lexical order == generation order, all unique).
$n = 2000; $s = [];
for ($i = 0; $i < $n; $i++) $s[] = (string) Uuid::uuid7();
$sorted = $s; sort($sorted, SORT_STRING);
var_dump($s === $sorted);
var_dump(count(array_unique($s)) === $n);

// rand_b fills all 62 bits: the variant byte's random portion must reach past 0x8f.
// A 60-bit seed would force b[8]'s top 2 random bits to 0, capping it at 0x8f.
$hi = 0;
for ($i = 0; $i < 4000; $i++) { $v = ord(Uuid::uuid7()->getBytes()[8]); if ($v > $hi) $hi = $v; }
var_dump($hi > 0x8f);

// getDateTime stays ms-only (ramsey-compat): decoded microseconds are always a 1000-multiple.
var_dump(((int) Uuid::uuid7()->getDateTime()->format('u')) % 1000 === 0);

// to* aliases mirror their get* counterparts exactly.
$u = Uuid::uuid4();
var_dump($u->toBytes() === $u->getBytes());
var_dump($u->toHexadecimal() === $u->getHex());
var_dump($u->toInteger() === $u->getInteger());
var_dump($u->toUrn() === $u->getUrn());
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
