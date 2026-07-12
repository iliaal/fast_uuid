--TEST--
Compat UUIDs retain Ramsey legacy Serializable methods alongside magic serialization
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Uuid;

$uuid = Uuid::fromString('00112233-4455-4677-8899-aabbccddeeff');
var_dump($uuid instanceof Serializable);
var_dump(method_exists($uuid, 'serialize'));
var_dump(method_exists($uuid, 'unserialize'));

$class = get_class($uuid);
$copy = (new ReflectionClass($class))->newInstanceWithoutConstructor();
$copy->unserialize($uuid->serialize());
var_dump($copy->equals($uuid));

$binaryCopy = (new ReflectionClass($class))->newInstanceWithoutConstructor();
$binaryCopy->unserialize($uuid->getBytes());
var_dump($binaryCopy->equals($uuid));

$v4 = Uuid::uuid4();
$invalid = (new ReflectionClass(get_class($v4)))->newInstanceWithoutConstructor();
try {
    $invalid->unserialize(Uuid::uuid1()->toString());
    var_dump(false);
} catch (FastUuid\Exception\InvalidArgumentException) {
    var_dump(true);
}

$invalidText = (new ReflectionClass(get_class($v4)))->newInstanceWithoutConstructor();
try {
    $invalidText->unserialize('0011223344556677');
    var_dump(false);
} catch (FastUuid\Exception\InvalidArgumentException) {
    var_dump(true);
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
