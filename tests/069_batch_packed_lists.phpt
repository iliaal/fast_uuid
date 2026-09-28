--TEST--
Batch generators return well-formed, unique, packed lists in string and binary form
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;

// name => [element length, RFC 9562 version]
$forms = [
    'uuid_v4_batch'     => [36, 4],
    'uuid_v7_batch'     => [36, 7],
    'uuid_v4_bin_batch' => [16, 4],
    'uuid_v7_bin_batch' => [16, 7],
];

function well_formed(string $s, int $len, int $ver): bool
{
    if (strlen($s) !== $len) {
        return false;
    }
    if ($len === 16) {
        return (ord($s[6]) >> 4) === $ver && (ord($s[8]) & 0xc0) === 0x80;
    }
    return uuid_is_valid($s) && $s[14] === (string) $ver && strpos('89ab', $s[19]) !== false;
}

foreach ($forms as $fn => [$len, $ver]) {
    $shape = true;
    $unique = true;
    $valid = true;
    foreach ([1, 2, 7, 8, 9, 63, 64, 65, 128, 129, 1000] as $n) {
        $a = $fn($n);
        $shape = $shape && count($a) === $n && array_is_list($a) && array_keys($a) === range(0, $n - 1);
        $unique = $unique && count(array_flip($a)) === $n;
        foreach ($a as $s) {
            $valid = $valid && is_string($s) && well_formed($s, $len, $ver);
        }
    }
    var_dump($shape);
    var_dump($unique);
    var_dump($valid);

    // A batch is an ordinary array: appends, removals, copies and serialization behave.
    $a = $fn(10);
    $copy = $a;
    $a[] = 'tail';
    $shape = count($a) === 11 && array_is_list($a) && count($copy) === 10;
    array_pop($a);
    $first = array_shift($a);
    $shape = $shape && $first === $copy[0] && array_is_list($a) && $a[0] === $copy[1];
    unset($a[3]);
    $shape = $shape && !array_is_list($a) && count($a) === 8 && count($copy) === 10;
    $copy[0] .= '!';
    $shape = $shape && $copy[0] !== $first;
    $b = $fn(10);
    $shape = $shape && unserialize(serialize($b)) === $b && str_starts_with(json_encode(array_map('bin2hex', $b)), '[');
    var_dump($shape);

    // The maximum batch size.
    $max = $fn(100000);
    var_dump(count($max) === 100000 && array_is_list($max) && count(array_flip($max)) === 100000
        && well_formed($max[0], $len, $ver) && well_formed($max[99999], $len, $ver));

    // Consecutive batches are distinct.
    var_dump(count(array_intersect($fn(200), $fn(200))) === 0);
}

// v7 batches stay monotonic within a batch and across consecutive batches.
foreach (['uuid_v7_batch', 'uuid_v7_bin_batch'] as $fn) {
    $prev = $fn(300);
    $sorted = $prev;
    sort($sorted, SORT_STRING);
    $ok = $prev === $sorted;
    for ($i = 0; $i < 20; $i++) {
        $next = $fn(300);
        $ok = $ok && strcmp($prev[299], $next[0]) < 0;
        $prev = $next;
    }
    var_dump($ok);
}

// Batch elements are ordinary refcounted strings: they survive being detached from the array.
$a = uuid_v4_batch(5);
$kept = $a[2];
unset($a);
var_dump(strlen($kept) === 36 && uuid_is_valid($kept) && Uuid::fromString($kept)->getVersion() === 4);
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
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
