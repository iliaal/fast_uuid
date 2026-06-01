--TEST--
fast_uuid: generation, format, round-trip, monotonic v7
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;

$u = Uuid::uuid4();
var_dump($u instanceof FastUuid\UuidInterface);
var_dump((bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', (string)$u));
var_dump($u->getVersion());
var_dump($u->getVariant());

// round-trip
$s = (string)$u;
var_dump(Uuid::fromString($s)->equals($u));
var_dump(strlen($u->getBytes()) === 16);
var_dump(Uuid::fromBytes($u->getBytes())->toString() === $s);

// procedural
var_dump((bool)preg_match('/-7[0-9a-f]{3}-/', uuid_v7()));
var_dump(uuid_is_valid(uuid_v1()));
var_dump(strlen(uuid_to_bin(uuid_v4())) === 16);

// v7 monotonic within a process
$a = uuid_v7(); $b = uuid_v7();
var_dump(strcmp($a, $b) < 0);

// v5 deterministic
var_dump(Uuid::uuid5(Uuid::NAMESPACE_DNS, "example.com")->toString()
       === Uuid::uuid5(Uuid::NAMESPACE_DNS, "example.com")->toString());

// json
var_dump(json_encode($u) === '"' . $s . '"');
?>
--EXPECT--
bool(true)
bool(true)
int(4)
int(2)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
