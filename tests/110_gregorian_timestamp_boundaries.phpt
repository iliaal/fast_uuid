--TEST--
Gregorian UUID timestamps retain floor semantics and fractional precision at boundaries
--EXTENSIONS--
fast_uuid
--SKIPIF--
<?php if (PHP_INT_SIZE < 8) die('skip 64-bit only: Gregorian boundary timestamps exceed 32-bit PHP_INT_MAX'); ?>
--FILE--
<?php
use FastUuid\Uuid;

foreach ([
    [
        ['00000000-0000-1000-8000-010203040506', '00000000-0000-6000-8000-010203040506'],
        [-12219292800, '000000', -12219292800000],
    ],
    [
        ['12e8a97f-1dd2-11b2-8000-010203040506', '1b21dd21-2e8a-697f-8000-010203040506'],
        [-2, '999999', -1001],
    ],
    [
        ['12e8a980-1dd2-11b2-8000-010203040506', '1b21dd21-2e8a-6980-8000-010203040506'],
        [-1, '000000', -1000],
    ],
    [
        ['138118f0-1dd2-11b2-8000-010203040506', '1b21dd21-3811-68f0-8000-010203040506'],
        [-1, '999000', -1],
    ],
    [
        ['13813fff-1dd2-11b2-8000-010203040506', '1b21dd21-3813-6fff-8000-010203040506'],
        [-1, '999999', -1],
    ],
    [
        ['13814000-1dd2-11b2-8000-010203040506', '1b21dd21-3814-6000-8000-010203040506'],
        [0, '000000', 0],
    ],
    [
        ['13814009-1dd2-11b2-8000-010203040506', '1b21dd21-3814-6009-8000-010203040506'],
        [0, '000000', 0],
    ],
    [
        ['1381400a-1dd2-11b2-8000-010203040506', '1b21dd21-3814-600a-8000-010203040506'],
        [0, '000001', 0],
    ],
    [
        ['1381670f-1dd2-11b2-8000-010203040506', '1b21dd21-3816-670f-8000-010203040506'],
        [0, '000999', 0],
    ],
    [
        ['13816710-1dd2-11b2-8000-010203040506', '1b21dd21-3816-6710-8000-010203040506'],
        [0, '001000', 1],
    ],
    [
        ['ffffffff-ffff-1fff-8000-010203040506', 'ffffffff-ffff-6fff-8000-010203040506'],
        [103072857660, '684697', 103072857660684],
    ],
    [
        ['00000000-0000-2000-8000-010203040506', 'ffffffff-0000-2000-8000-010203040506'],
        [-12219292800, '000000', -12219292800000],
    ],
    [
        ['00000000-1dd2-21b2-8000-010203040506', 'ffffffff-1dd2-21b2-8000-010203040506'],
        [-33, '276236', -32724],
    ],
    [
        ['00000000-1dd3-21b2-8000-010203040506', 'ffffffff-1dd3-21b2-8000-010203040506'],
        [396, '772966', 396772],
    ],
    [
        ['00000000-ffff-2fff-8000-010203040506', 'ffffffff-ffff-2fff-8000-010203040506'],
        [103072857231, '187968', 103072857231187],
    ],
] as [$strings, $expected]) {
    foreach ($strings as $string) {
        $uuid = Uuid::fromString($string);
        $date = $uuid->getDateTime();
        var_dump([$date->getTimestamp(), $date->format('u'), $uuid->getTimestampMillis()] === $expected);
    }
}
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
bool(true)
bool(true)
bool(true)
