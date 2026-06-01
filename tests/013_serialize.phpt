--TEST--
Core UUID serialize/unserialize round-trip and strict (no dynamic) properties
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;

$u = Uuid::uuid4();
$v = unserialize(serialize($u));
var_dump($v instanceof Uuid);
var_dump($u->equals($v));
var_dump((string) $u === (string) $v);

// every version survives a serialize round-trip (not just v4)
foreach ([Uuid::fromString(Uuid::NIL), Uuid::uuid1(), Uuid::uuid6(), Uuid::uuid7()] as $x) {
    var_dump(unserialize(serialize($x))->equals($x));
}

// strict-properties: a dynamic property is an Error, not silently accepted
try { $u->foo = 1; var_dump(false); } catch (\Error $e) { var_dump(true); }

// a malformed serialized payload (wrong byte length) is rejected
$bad = 'O:13:"FastUuid\Uuid":1:{i:0;s:5:"short";}';
try { unserialize($bad); var_dump(false); }
catch (\FastUuid\Exception\InvalidArgumentException $e) { var_dump(true); }
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
