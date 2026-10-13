--TEST--
v1 and v6 keep timestamps unique with a fixed node and clock sequence
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;

foreach (['uuid1' => 1, 'uuid6' => 6] as $method => $version) {
    $uuids = [];
    for ($i = 0; $i < 100000; $i++) {
        $uuids[] = Uuid::$method(1, 7);
    }

    $seen = [];
    $fieldsOk = true;
    foreach ($uuids as $uuid) {
        $bytes = $uuid->getBytes();
        $seen[$bytes] = true;
        $fieldsOk = $fieldsOk && $uuid->getVersion() === $version
            && substr($bytes, 8) === hex2bin('8007000000000001');
    }
    var_dump(count($seen) === 100000);
    var_dump($fieldsOk);
}

function timestampHex(string $bytes): string {
    $hex = bin2hex($bytes);
    return (ord($bytes[6]) >> 4) === 1
        ? substr($hex, 13, 3) . substr($hex, 8, 4) . substr($hex, 0, 8)
        : substr($hex, 0, 12) . substr($hex, 13, 3);
}

$ordered = true;
$last = '';
for ($i = 0; $i < 1000; $i++) {
    foreach ([Uuid::uuid1(1, 7), Uuid::uuid6(1, 7)] as $uuid) {
        $timestamp = timestampHex($uuid->getBytes());
        $ordered = $ordered && strcmp($timestamp, $last) > 0;
        $last = $timestamp;
    }
}
var_dump($ordered);

$defaultsOk = true;
foreach ([Uuid::uuid1(), Uuid::uuid6(), Uuid::fromString(uuid_v1()), Uuid::fromString(uuid_v6())] as $uuid) {
    $bytes = $uuid->getBytes();
    $defaultsOk = $defaultsOk && in_array($uuid->getVersion(), [1, 6], true)
        && (ord($bytes[8]) & 0xc0) === 0x80
        && (ord($bytes[10]) & 1) === 1;
}
var_dump($defaultsOk);

$date = new DateTimeImmutable('2020-01-02 03:04:05.123456+00:00');
$explicit = Uuid::fromDateTime($date, 1, 7);
var_dump($explicit->equals(Uuid::fromDateTime($date, 1, 7)));
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
