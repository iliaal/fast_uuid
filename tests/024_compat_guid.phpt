--TEST--
compat: Guid mixed-endian byte order and GuidStringCodec round-trip
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Uuid;
use FastUuid\Compat\Guid\Guid;
use FastUuid\Compat\Codec\GuidStringCodec;
use FastUuid\Compat\UuidFactory;

$u4 = Uuid::uuid4();
$g = new Guid($u4);
$raw = $u4->getBytes();
$gbytes = $g->getBytes();

var_dump($gbytes[0] === $raw[3] && $gbytes[3] === $raw[0] && substr($gbytes, 8) === substr($raw, 8));
var_dump((new GuidStringCodec())->decodeBytes($gbytes)->equals($u4));
var_dump(strlen($g->toString()) === 36);

$fixed = Uuid::fromString('00112233-4455-4677-8899-aabbccddeeff');
$codec = new GuidStringCodec();
$factory = new UuidFactory();
$factory->setCodec($codec);
$swapped = '33221100-5544-7746-8899-aabbccddeeff';
var_dump($factory->fromString($codec->encode($fixed))->equals($fixed));
var_dump($factory->fromBytes($codec->encodeBinary($fixed))->equals($fixed));
var_dump($factory->fromHexadecimal($fixed->getHex())->toString() === $swapped);
var_dump($factory->fromInteger((string) $fixed->getInteger())->toString() === $swapped);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
