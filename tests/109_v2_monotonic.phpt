--TEST--
uuid2 with fixed node and clockseq stays unique on a stalled clock
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;

$node = '010203040506';
$clockseq = 0x1234;

// Fully supplied node+clockseq: with a raw wall clock these repeat whenever
// the clock stalls within one 100 ns tick, so a tight loop used to return the
// same UUID over and over. The v2 high-water mark advances the visible 28-bit
// timestamp instead.
$uuids = [];
for ($i = 0; $i < 20000; $i++) {
    $uuids[] = Uuid::uuid2(1, 7, $node, $clockseq);
}

$seen = [];
foreach ($uuids as $uuid) {
    $seen[$uuid->getBytes()] = true;
}
var_dump(count($seen));

// field checks: version 2, local id in time_low, local domain in
// clock_seq_low (v2 overwrites it), RFC 4122 variant, supplied node and
// clock_seq_hi preserved
$ok = true;
foreach (array_slice($uuids, 0, 100) as $uuid) {
    $bytes = $uuid->getBytes();
    $ok = $ok && $uuid->getVersion() === 2
        && substr($bytes, 0, 4) === hex2bin('00000007')
        && ord($bytes[9]) === 1
        && ord($bytes[8]) === 0x92
        && (ord($bytes[8]) & 0xc0) === 0x80
        && substr($bytes, 10, 6) === hex2bin($node);
}
var_dump($ok);

// visible 28-bit timestamp: time_mid (bytes 4-5) + time_hi (byte 6 low nibble, byte 7);
// strictly increasing while the clock is stalled
function visibleTs(string $bytes): string {
    $hex = bin2hex($bytes);
    return substr($hex, 8, 4) . substr($hex, 13, 3);
}

$ordered = true;
$last = '';
for ($i = 0; $i < 1000; $i++) {
    $ts = visibleTs(Uuid::uuid2(1, 7, $node, $clockseq)->getBytes());
    $ordered = $ordered && strcmp($ts, $last) > 0;
    $last = $ts;
}
var_dump($ordered);
?>
--EXPECT--
int(20000)
bool(true)
bool(true)
