--TEST--
Procedural UUID functions: validity, parsed version, binary conversion, random bytes
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;

var_dump(uuid_is_valid(uuid_v1()));
var_dump(uuid_is_valid(uuid_v4()));
var_dump(uuid_is_valid(uuid_v4_fast()));
var_dump(uuid_is_valid(uuid_v6()));
var_dump(uuid_is_valid(uuid_v7()));

var_dump(Uuid::fromString(uuid_v1())->getVersion() === 1);
var_dump(Uuid::fromString(uuid_v4())->getVersion() === 4);
var_dump(Uuid::fromString(uuid_v4_fast())->getVersion() === 4);
var_dump(Uuid::fromString(uuid_v6())->getVersion() === 6);
var_dump(Uuid::fromString(uuid_v7())->getVersion() === 7);

$s = uuid_v4();
$bin = uuid_to_bin($s);
var_dump(strlen($bin) === 16);
var_dump($bin === Uuid::fromString($s)->getBytes());

var_dump(Uuid::fromBytes(uuid_to_bin($s))->toString() === $s);

var_dump(uuid_from_bin(uuid_to_bin($s)) === $s);
var_dump(uuid_from_bin($bin) === $s);

var_dump(uuid_is_valid('garbage') === false);

// Batched CSPRNG: exact lengths, including a large batch crossing buffer refills.
var_dump(strlen(fast_uuid_random_bytes(16)) === 16);
var_dump(
    strlen(fast_uuid_random_bytes(4096)) === 4096
    && strlen(fast_uuid_random_bytes(4097)) === 4097
    && strlen(fast_uuid_random_bytes(8191)) === 8191
    && strlen(fast_uuid_random_bytes(8192)) === 8192
);
var_dump(strlen(fast_uuid_random_bytes(100000)) === 100000);

$threw = false;
try { fast_uuid_random_bytes(0); } catch (\InvalidArgumentException $x) { $threw = true; }
var_dump($threw);
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
