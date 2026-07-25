--TEST--
compat: configured codecs retain Ramsey text and binary representations
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Codec\GuidStringCodec;
use FastUuid\Compat\Codec\OrderedTimeCodec;
use FastUuid\Compat\Guid\Guid;
use FastUuid\Compat\Uuid;
use FastUuid\Compat\UuidFactory;

$canonical = '00112233-4455-1677-8899-aabbccddeeff';
$uuid = Uuid::fromString($canonical);

$ordered = new OrderedTimeCodec();
$orderedBytes = hex2bin('16774455001122338899aabbccddeeff');
$orderedFactory = new UuidFactory();
$orderedFactory->setCodec($ordered);
$orderedUuid = $orderedFactory->fromBytes($orderedBytes);
var_dump($ordered->encodeBinary($uuid) === $orderedBytes);
var_dump($orderedUuid->getBytes() === $orderedBytes);
var_dump($orderedUuid->toString() === $canonical);

$guid = new GuidStringCodec();
$networkBytes = hex2bin('00112233445516778899aabbccddeeff');
$guidText = '33221100-5544-7716-8899-aabbccddeeff';
$guidFactory = new UuidFactory();
$guidFactory->setCodec($guid);
var_dump($guid->encodeBinary($uuid) === $networkBytes);
var_dump($guid->decodeBytes($networkBytes)->equals($uuid));
var_dump($guid->encode($uuid) === $guidText);
var_dump($guidFactory->fromString($guidText)->toString() === $guidText);
var_dump($guidFactory->fromBytes($networkBytes)->getBytes() === $networkBytes);

$adapter = new Guid($uuid);
var_dump(bin2hex($adapter->getBytes()) === '33221100554477168899aabbccddeeff');
var_dump($adapter->toString() === $guidText);

Uuid::setFactory($guidFactory);
$roundTrip = unserialize(serialize(Uuid::fromString($guidText)));
// Portable payload is network-order bytes; presentation codec is not restored.
// Identity (core) must match the UUID that Guid text decoded to.
var_dump($roundTrip->getCore()->toString() === $canonical);
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
