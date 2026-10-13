--TEST--
v7 siblings use bounded random increments while preserving order across APIs
--EXTENSIONS--
fast_uuid
--SKIPIF--
<?php if (PHP_INT_SIZE < 8) die('skip 64-bit rand_b arithmetic'); ?>
--FILE--
<?php
declare(strict_types=1);

function keyOf(string $bytes): int {
    $ms = 0;
    for ($i = 0; $i < 6; $i++) {
        $ms = $ms * 256 + ord($bytes[$i]);
    }
    return $ms * 4096 + ((ord($bytes[6]) & 15) << 8) + ord($bytes[7]);
}

function randB(string $bytes): int {
    $value = ord($bytes[8]) & 63;
    for ($i = 9; $i < 16; $i++) {
        $value = $value * 256 + ord($bytes[$i]);
    }
    return $value;
}

foreach ([false, true] as $binary) {
    $batch = $binary ? uuid_v7_bin_batch(512) : array_map('uuid_to_bin', uuid_v7_batch(512));
    $ordered = true;
    $bounded = true;
    $steps = [];
    for ($i = 1; $i < count($batch); $i++) {
        $previous = $batch[$i - 1];
        $current = $batch[$i];
        $ordered = $ordered && strcmp($current, $previous) > 0;
        $keyDifference = keyOf($current) - keyOf($previous);
        $step = $keyDifference === 1
            ? (0x3fffffffffffffff - randB($previous)) + 1 + randB($current)
            : randB($current) - randB($previous);
        $bounded = $bounded && ($keyDifference === 0 || $keyDifference === 1)
            && $step >= 1 && $step <= 0x1000000;
        $steps[$step] = true;
    }
    var_dump($ordered);
    var_dump(count(array_unique($batch)) === 512);
    var_dump($bounded);
    var_dump(count($steps) > 1);
}

$previous = uuid_v7_bin();
$ordered = true;
for ($i = 0; $i < 256; $i++) {
    foreach ([uuid_to_bin(uuid_v7()), FastUuid\Uuid::uuid7()->getBytes(),
            ...uuid_v7_bin_batch(2), uuid_v7_bin()] as $current) {
        $ordered = $ordered && strcmp($current, $previous) > 0;
        $previous = $current;
    }
}
var_dump($ordered);

$ms = 1700000000123;
$date = new DateTimeImmutable('2023-11-14T22:13:20.123456Z');
$explicit = true;
for ($i = 0; $i < 32; $i++) {
    $explicit = $explicit && keyOf(uuid_v7_at_bin($ms)) === $ms * 4096
        && keyOf(uuid_to_bin(uuid_v7_at($ms))) === $ms * 4096
        && keyOf(FastUuid\Uuid::uuid7($ms)->getBytes()) === $ms * 4096
        && keyOf(FastUuid\Uuid::uuid7($date)->getBytes()) === $ms * 4096 + intdiv(456 * 4096, 1000);
}
var_dump($explicit);
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
