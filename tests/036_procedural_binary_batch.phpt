--TEST--
Procedural binary and batch UUID APIs
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;

function is_rfc_bytes(string $b, int $version): bool {
    return strlen($b) === 16
        && ((ord($b[6]) >> 4) & 0x0f) === $version
        && (ord($b[8]) & 0xc0) === 0x80;
}

// Binary generator counterparts return raw RFC bytes.
var_dump(is_rfc_bytes(uuid_v1_bin(), 1));
var_dump(is_rfc_bytes(uuid_v4_bin(), 4));
var_dump(is_rfc_bytes(uuid_v4_fast_bin(), 4));
var_dump(is_rfc_bytes(uuid_v6_bin(), 6));
var_dump(is_rfc_bytes(uuid_v7_bin(), 7));

// Explicit v7 timestamp survives the binary fast path.
$ms = 1700000000123;
var_dump(Uuid::fromBytes(uuid_v7_at_bin($ms))->getTimestampMillis() === $ms);

// Name-based binary forms match the canonical RFC vectors.
var_dump(bin2hex(uuid_v3_bin(Uuid::NAMESPACE_DNS, 'www.example.com')) === '5df418813aed351588a72f4a814cf09e');
var_dump(bin2hex(uuid_v5_bin(Uuid::NAMESPACE_DNS, 'www.example.com')) === '2ed6657de927568b95e12665a8aea6a2');

// v8 binary form forces version/variant bits identically to the string form.
$bytes = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f";
var_dump(uuid_v8_bin($bytes) === uuid_to_bin(uuid_v8($bytes)));
var_dump(is_rfc_bytes(uuid_v8_bin($bytes), 8));

// Batch string forms have the requested count and valid versions.
$v4 = uuid_v4_batch(4);
var_dump(count($v4) === 4);
var_dump(array_reduce($v4, fn(bool $ok, string $uuid): bool => $ok && uuid_is_valid($uuid) && Uuid::fromString($uuid)->getVersion() === 4, true));

$v7 = uuid_v7_batch(6);
$sorted = $v7;
sort($sorted, SORT_STRING);
var_dump(count($v7) === 6);
var_dump($v7 === $sorted);
var_dump(array_reduce($v7, fn(bool $ok, string $uuid): bool => $ok && uuid_is_valid($uuid) && Uuid::fromString($uuid)->getVersion() === 7, true));

// Batch binary forms have the requested count and valid versions.
$v4b = uuid_v4_bin_batch(4);
var_dump(count($v4b) === 4);
var_dump(array_reduce($v4b, fn(bool $ok, string $b): bool => $ok && is_rfc_bytes($b, 4), true));

$v7b = uuid_v7_bin_batch(6);
$sorted = $v7b;
sort($sorted, SORT_STRING);
var_dump(count($v7b) === 6);
var_dump($v7b === $sorted);
var_dump(array_reduce($v7b, fn(bool $ok, string $b): bool => $ok && is_rfc_bytes($b, 7), true));

// Invalid batch counts throw the extension's argument exception.
foreach ([0, -1] as $bad) {
    $threw = false;
    try { uuid_v4_batch($bad); } catch (\FastUuid\Exception\InvalidArgumentException) { $threw = true; }
    var_dump($threw);
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
