--TEST--
Compat name-based UUIDs decode string namespaces through the configured codec
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Codec\GuidStringCodec;
use FastUuid\Compat\UuidFactory;

$factory = new UuidFactory();
$factory->setCodec(new GuidStringCodec());
$namespace = '33221100-5544-6677-8899-aabbccddeeff';
$decoded = $factory->fromString($namespace);

var_dump($factory->uuid3($namespace, 'name')->equals($factory->uuid3($decoded, 'name')));
var_dump($factory->uuid5($namespace, 'name')->equals($factory->uuid5($decoded, 'name')));
?>
--EXPECT--
bool(true)
bool(true)
