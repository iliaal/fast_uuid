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

// A GUID is mixed-endian in its byte array only. The text form is the plain
// RFC string, so encode()/decode() and every identity accessor stay canonical.
$guid = new GuidStringCodec();
$networkBytes = hex2bin('00112233445516778899aabbccddeeff');
$guidBytes = hex2bin('33221100554477168899aabbccddeeff');
$guidFactory = new UuidFactory();
$guidFactory->setCodec($guid);
var_dump($guid->encodeBinary($uuid) === $guidBytes);
var_dump($guid->decodeBytes($guidBytes)->equals($uuid));
var_dump($guid->encode($uuid) === $canonical);
var_dump($guidFactory->fromString($canonical)->toString() === $canonical);
var_dump($guidFactory->fromBytes($guidBytes)->getBytes() === $guidBytes);
var_dump($guidFactory->fromBytes($guidBytes)->getCore()->getBytes() === $networkBytes);

$adapter = new Guid($uuid);
var_dump(bin2hex($adapter->getBytes()) === '33221100554477168899aabbccddeeff');
var_dump($adapter->toString() === $canonical);

Uuid::setFactory($guidFactory);
$roundTrip = unserialize(serialize(Uuid::fromString($canonical)));
// The payload is network-order bytes and the active factory's codec is
// re-attached, so both identity and presentation survive the round trip.
var_dump($roundTrip->getCore()->toString() === $canonical);
var_dump($roundTrip->toString() === $canonical);
var_dump($roundTrip->getBytes() === $guidBytes);
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
