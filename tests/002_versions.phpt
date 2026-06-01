--TEST--
fast_uuid: version + variant for every generator, NIL/MAX, determinism
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;

$v1 = Uuid::uuid1();
var_dump($v1->getVersion() === 1);
var_dump($v1->getVariant() === 2);
var_dump(strlen((string)$v1) === 36);

$v3 = Uuid::uuid3(Uuid::NAMESPACE_DNS, 'example.com');
var_dump($v3->getVersion() === 3);
var_dump($v3->getVariant() === 2);
var_dump(strlen((string)$v3) === 36);

$v4 = Uuid::uuid4();
var_dump($v4->getVersion() === 4);
var_dump($v4->getVariant() === 2);
var_dump(strlen((string)$v4) === 36);

$v5 = Uuid::uuid5(Uuid::NAMESPACE_DNS, 'example.com');
var_dump($v5->getVersion() === 5);
var_dump($v5->getVariant() === 2);
var_dump(strlen((string)$v5) === 36);

$v6 = Uuid::uuid6();
var_dump($v6->getVersion() === 6);
var_dump($v6->getVariant() === 2);
var_dump(strlen((string)$v6) === 36);

$v7 = Uuid::uuid7();
var_dump($v7->getVersion() === 7);
var_dump($v7->getVariant() === 2);
var_dump(strlen((string)$v7) === 36);

// NIL: no version, NCS variant
$nil = Uuid::fromString(Uuid::NIL);
var_dump($nil->getVersion() === null);
var_dump($nil->getVariant() === 0);

// MAX: no version
$max = Uuid::fromString(Uuid::MAX);
var_dump($max->getVersion() === null);

// name-based v3 is deterministic
var_dump(Uuid::uuid3(Uuid::NAMESPACE_DNS, 'example.com')->toString()
       === Uuid::uuid3(Uuid::NAMESPACE_DNS, 'example.com')->toString());

// random v4 twice are not equal
var_dump(Uuid::uuid4()->equals(Uuid::uuid4()) === false);
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
