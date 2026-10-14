--TEST--
uuid2 keeps the visible timestamp unique and increasing with fixed arguments
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;

$uuids = [];
for ($i = 0; $i < 100000; $i++) {
    $uuids[] = Uuid::uuid2(1, 7);
}

$seen = [];
$fieldsOk = true;
foreach ($uuids as $uuid) {
    $bytes = $uuid->getBytes();
    $seen[$bytes] = true;
    $fieldsOk = $fieldsOk && $uuid->getVersion() === 2
        && substr($bytes, 0, 4) === hex2bin('00000007')
        && ord($bytes[9]) === 1
        && (ord($bytes[8]) & 0xc0) === 0x80;
}
var_dump(count($seen) === 100000);
var_dump($fieldsOk);

// visible 28-bit timestamp: time_mid (bytes 4-5) + time_hi (byte 6 low nibble, byte 7)
function visibleTs(string $bytes): string {
    $hex = bin2hex($bytes);
    return substr($hex, 8, 4) . substr($hex, 13, 3);
}

$ordered = true;
$last = '';
for ($i = 0; $i < 1000; $i++) {
    $ts = visibleTs(Uuid::uuid2(1, 7)->getBytes());
    $ordered = $ordered && strcmp($ts, $last) > 0;
    $last = $ts;
}
var_dump($ordered);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
