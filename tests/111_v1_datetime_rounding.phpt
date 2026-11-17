--TEST--
v1/v6 getDateTime() rounds 100 ns ticks to the nearest microsecond; getTimestampMillis() floors
--EXTENSIONS--
fast_uuid
--FILE--
<?php
// Each v1/v6 UUID below is 1,700,000,000 s after the Unix epoch plus the 100 ns ticks in the key, or a few ticks
// before the epoch.
$cases = [
    '1234564' => ['04c29684-833b-11ee-8000-000000000001', 1700000000123],
    '1234565' => ['04c29685-833b-11ee-8000-000000000001', 1700000000123],
    '1234567' => ['04c29687-833b-11ee-8000-000000000001', 1700000000123],
    '9999994' => ['0548567a-833b-11ee-8000-000000000001', 1700000000999],
    '9999995' => ['0548567b-833b-11ee-8000-000000000001', 1700000000999],
];
foreach ($cases as $ticks => [$s, $ms]) {
    $u = FastUuid\Uuid::fromString($s);
    echo $ticks, ' ', $u->getDateTime()->format('Y-m-d H:i:s.u'), "\n";
    // Millisecond timestamps this large do not fit a 32-bit int.
    var_dump(PHP_INT_SIZE < 8 || $u->getTimestampMillis() === $ms);
}
echo FastUuid\Uuid::fromString('1ee833b0-4c29-6687-8000-000000000001')->getDateTime()->format('Y-m-d H:i:s.u'), "\n";
echo FastUuid\Uuid::fromString('13813ffb-1dd2-11b2-8000-000000000001')->getDateTime()->format('Y-m-d H:i:s.u'), "\n";
echo FastUuid\Uuid::fromString('13813ffa-1dd2-11b2-8000-000000000001')->getDateTime()->format('Y-m-d H:i:s.u'), "\n";
?>
--EXPECT--
1234564 2023-11-14 22:13:20.123456
bool(true)
1234565 2023-11-14 22:13:20.123457
bool(true)
1234567 2023-11-14 22:13:20.123457
bool(true)
9999994 2023-11-14 22:13:20.999999
bool(true)
9999995 2023-11-14 22:13:21.000000
bool(true)
2023-11-14 22:13:20.123457
1970-01-01 00:00:00.000000
1969-12-31 23:59:59.999999
