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

// fu-3p7: legacy TEXT payloads restore the same core identity even when the
// process-global factory codec reorders bytes (COMB); presentation stays
// codec-shaped while the wrapped core is untouched.
$combFactory = new FastUuid\Compat\UuidFactory();
$combFactory->setCodec(new FastUuid\Compat\Codec\TimestampFirstCombCodec());
Uuid::setFactory($combFactory);
$v1legacy = Uuid::uuid1();
$coreText = $v1legacy->getCore()->toString();
$legacyCopy = (new ReflectionClass(get_class($v1legacy)))->newInstanceWithoutConstructor();
$legacyCopy->unserialize($coreText);
var_dump($legacyCopy->getCore()->toString() === $coreText);
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
