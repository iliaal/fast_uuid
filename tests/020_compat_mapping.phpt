--TEST--
compat: version-to-class mapping, nil/max/nonstandard, serialize round-trip, getCore
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Uuid;
use FastUuid\Compat\Rfc4122\UuidV1;
use FastUuid\Compat\Rfc4122\UuidV2;
use FastUuid\Compat\Rfc4122\UuidV3;
use FastUuid\Compat\Rfc4122\UuidV4;
use FastUuid\Compat\Rfc4122\UuidV5;
use FastUuid\Compat\Rfc4122\UuidV6;
use FastUuid\Compat\Rfc4122\UuidV7;
use FastUuid\Compat\Rfc4122\NilUuid;
use FastUuid\Compat\Rfc4122\MaxUuid;
use FastUuid\Compat\Nonstandard\Uuid as NonstandardUuid;

var_dump(Uuid::uuid1() instanceof UuidV1);
var_dump(Uuid::uuid2(Uuid::DCE_DOMAIN_PERSON) instanceof UuidV2);
var_dump(Uuid::uuid3(Uuid::NAMESPACE_DNS, 'a') instanceof UuidV3);
var_dump(Uuid::uuid4() instanceof UuidV4);
var_dump(Uuid::uuid5(Uuid::NAMESPACE_DNS, 'a') instanceof UuidV5);
var_dump(Uuid::uuid6() instanceof UuidV6);
var_dump(Uuid::uuid7() instanceof UuidV7);

var_dump(Uuid::fromString(Uuid::NIL) instanceof NilUuid);
var_dump(Uuid::fromString(Uuid::MAX) instanceof MaxUuid);

$ns = Uuid::fromBytes("\x12\x34\x56\x78\x9a\xbc\x4d\xef\xc0\x11\x22\x33\x44\x55\x66\x77");
var_dump($ns instanceof NonstandardUuid);

$u4 = Uuid::uuid4();
$un = unserialize(serialize($u4));
var_dump($un instanceof UuidV4);
var_dump($un->equals($u4));

var_dump($u4->getCore() instanceof \FastUuid\Uuid);
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
