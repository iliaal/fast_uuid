--TEST--
equals / compareTo ordering, clone independence, and sorting
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;

$a = Uuid::uuid4();
$b = clone $a;

// A clone equals the original, by object and by string form.
var_dump($a->equals($b));
var_dump($a->equals((string) $b));
var_dump($a->compareTo($b) === 0);

// Reflexive equality.
var_dump($a->equals($a));

// The clone is an independent object that still compares equal.
var_dump($a !== $b);
var_dump($b->equals($a));

// Fixed values give a deterministic ordering. compareTo returns -1/0/1.
$lo  = Uuid::fromString('00000000-0000-4000-8000-000000000001');
$hi  = Uuid::fromString('00000000-0000-4000-8000-000000000002');
$far = Uuid::fromString('ff000000-0000-4000-8000-000000000000');

var_dump($lo->compareTo($hi) < 0);
var_dump($hi->compareTo($lo) > 0);
var_dump($lo->compareTo($lo) === 0);
var_dump($lo->compareTo($far) < 0);

// Sorting an unordered array with compareTo yields ascending order.
$arr = [
    Uuid::fromString('00000000-0000-4000-8000-000000000003'),
    Uuid::fromString('00000000-0000-4000-8000-000000000001'),
    Uuid::fromString('00000000-0000-4000-8000-000000000002'),
];
usort($arr, fn($x, $y) => $x->compareTo($y));
echo implode("\n", array_map(fn($u) => (string) $u, $arr)), "\n";
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
00000000-0000-4000-8000-000000000001
00000000-0000-4000-8000-000000000002
00000000-0000-4000-8000-000000000003
