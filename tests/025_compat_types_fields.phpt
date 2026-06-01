--TEST--
compat: Type\Hexadecimal, Type\Integer, Rfc4122\Fields accessors
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Uuid;
use FastUuid\Compat\Rfc4122\FieldsInterface;
use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Compat\Type\Integer as IntegerType;

$u4 = Uuid::uuid4();

var_dump($u4->getFields() instanceof FieldsInterface);

$hex = $u4->getHex();
var_dump($hex instanceof Hexadecimal);
var_dump((string) $hex === bin2hex($u4->getBytes()));

$int = $u4->getInteger();
var_dump($int instanceof IntegerType);
var_dump((bool) preg_match('/^[0-9]+$/', (string) $int));
var_dump((string) $int === $u4->getCore()->getInteger());

$fields = $u4->getFields();
var_dump($fields->getVersion() === 4);
var_dump($fields->getVariant() === 2);
var_dump($fields->getNode() instanceof Hexadecimal);
var_dump($fields->getTimeLow() instanceof Hexadecimal);
var_dump($fields->getTimeMid() instanceof Hexadecimal);
var_dump($fields->getTimeHiAndVersion() instanceof Hexadecimal);
var_dump($fields->getClockSeqHiAndReserved() instanceof Hexadecimal);
var_dump($fields->getClockSeqLow() instanceof Hexadecimal);

$nil = Uuid::fromString(Uuid::NIL);
var_dump($nil->getFields()->isNil() === true);
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
