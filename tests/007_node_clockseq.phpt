--TEST--
uuid1/uuid6 honor explicit node and clockSeq arguments
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;

$node = hex2bin('010203040506');

// v1 with a hex-string node: node occupies the last 6 bytes.
$u1 = Uuid::uuid1('010203040506');
var_dump($u1->getVersion() === 1);
var_dump(substr($u1->getBytes(), 10, 6) === $node);

// An integer node is accepted and lands in the same place.
$u1i = Uuid::uuid1(0x010203040506);
var_dump(substr($u1i->getBytes(), 10, 6) === $node);

// v6 also places the explicit node in the last 6 bytes.
$u6 = Uuid::uuid6('010203040506');
var_dump($u6->getVersion() === 6);
var_dump(substr($u6->getBytes(), 10, 6) === $node);

// An explicit clockSeq is stable across calls (bytes 8..9, variant bits set).
$a = Uuid::uuid1('010203040506', 0x1234);
$b = Uuid::uuid1('010203040506', 0x1234);
var_dump(substr($a->getBytes(), 8, 2) === substr($b->getBytes(), 8, 2));
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
