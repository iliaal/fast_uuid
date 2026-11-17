--TEST--
v1/v6 getDateTime() rounds 100 ns ticks to the nearest microsecond; getTimestampMillis() floors
--EXTENSIONS--
fast_uuid
--FILE--
<?php
const GREG = 122192928000000000;

function v1(int $t60): FastUuid\Uuid {
    return FastUuid\Uuid::fromString(sprintf('%08x-%04x-%04x-8000-000000000001',
        $t60 & 0xffffffff, ($t60 >> 32) & 0xffff, (($t60 >> 48) & 0x0fff) | 0x1000));
}

function v6(int $t60): FastUuid\Uuid {
    $h = sprintf('%015x', $t60);
    return FastUuid\Uuid::fromString(substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-6' . substr($h, 12, 3) . '-8000-000000000001');
}

$base = GREG + 1700000000 * 10000000;
foreach ([1234564, 1234565, 1234567, 9999994, 9999995] as $ticks) {
    $u = v1($base + $ticks);
    echo $ticks, ' ', $u->getDateTime()->format('Y-m-d H:i:s.u'), ' ', $u->getTimestampMillis(), "\n";
}
echo v6($base + 1234567)->getDateTime()->format('Y-m-d H:i:s.u'), "\n";
echo v1(GREG - 5)->getDateTime()->format('Y-m-d H:i:s.u'), "\n";
echo v1(GREG - 6)->getDateTime()->format('Y-m-d H:i:s.u'), "\n";
?>
--EXPECT--
1234564 2023-11-14 22:13:20.123456 1700000000123
1234565 2023-11-14 22:13:20.123457 1700000000123
1234567 2023-11-14 22:13:20.123457 1700000000123
9999994 2023-11-14 22:13:20.999999 1700000000999
9999995 2023-11-14 22:13:21.000000 1700000000999
2023-11-14 22:13:20.123457
1970-01-01 00:00:00.000000
1969-12-31 23:59:59.999999
