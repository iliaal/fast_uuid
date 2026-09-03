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
function key_of(string $b): int { return ms_of($b) * 4096 + rand_a($b); }
function randb_of(string $b): int {
    return (ord($b[8]) & 0x3f) * 72057594037927936 + ord($b[9]) * 281474976710656
        + ord($b[10]) * 1099511627776 + ord($b[11]) * 4294967296 + ord($b[12]) * 16777216
        + ord($b[13]) * 65536 + ord($b[14]) * 256 + ord($b[15]);
}

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

// Batch path: one clock read for the whole batch, then the monotonic
// (key, rand_b) counter advances in pure C. Order, uniqueness and the exact
// +1 counter step are structural — no wall-clock statistics involved.
$n = 256;
$batch = uuid_v7_batch($n);
var_dump(count($batch) === $n);
var_dump(count(array_unique($batch)) === $n);
$sorted = $batch;
sort($sorted, SORT_STRING);
var_dump($batch === $sorted);
var_dump(array_reduce($batch, fn(bool $ok, string $s): bool => $ok && uuid_is_valid($s) && Uuid::fromString($s)->getVersion() === 7, true));
$prev = uuid_to_bin($batch[0]);
$stepsOk = true;
for ($i = 1; $i < $n; $i++) {
    $cur = uuid_to_bin($batch[$i]);
    $k0 = key_of($prev);
    $k1 = key_of($cur);
    $r0 = randb_of($prev);
    $r1 = randb_of($cur);
    $stepsOk = $stepsOk && (($k1 === $k0 && $r1 === $r0 + 1) || ($k1 === $k0 + 1 && $r1 === 0));
    $prev = $cur;
}
var_dump($stepsOk);

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
bool(true)
bool(true)
