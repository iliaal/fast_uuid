--TEST--
FastUuid factories return a rich UUID interface without breaking the namespace marker
--EXTENSIONS--
fast_uuid
--FILE--
<?php
$required = [
    'toString',
    'getBytes',
    'getHex',
    'getUrn',
    'getVersion',
    'getVariant',
    'getInteger',
    'getDateTime',
    'getTimestampMillis',
    'getFields',
    'equals',
    'compareTo',
];

$marker = new ReflectionClass(FastUuid\UuidInterface::class);
$interface = new ReflectionClass(FastUuid\UuidValueInterface::class);
var_dump($marker->hasMethod('getBytes') === false);
foreach ($required as $method) {
    var_dump($interface->hasMethod($method));
}

$returnType = (string) (new ReflectionMethod(FastUuid\Uuid::class, 'uuid4'))->getReturnType();
var_dump($returnType === FastUuid\UuidValueInterface::class);
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
