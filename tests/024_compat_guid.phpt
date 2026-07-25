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
var_dump((new GuidStringCodec())->decodeBytes($raw)->equals($u4));
var_dump(strlen($g->toString()) === 36);

$fixed = Uuid::fromString('00112233-4455-4677-8899-aabbccddeeff');
$codec = new GuidStringCodec();
$factory = new UuidFactory();
$factory->setCodec($codec);
var_dump($factory->fromString($codec->encode($fixed))->equals($fixed));
var_dump($factory->fromBytes($codec->encodeBinary($fixed))->equals($fixed));
// Identity forms must stay network-order even when the factory codec is Guid
// (toString-only checks are false-green under double-swap).
$viaHex = $factory->fromHexadecimal($fixed->getHex());
$viaInt = $factory->fromInteger((string) $fixed->getInteger());
var_dump($viaHex->equals($fixed));
var_dump($viaInt->equals($fixed));
var_dump($viaHex->getCore()->getBytes() === $fixed->getCore()->getBytes());
var_dump($viaInt->getCore()->getBytes() === $fixed->getCore()->getBytes());
$gwrap = $factory->uuid4();
var_dump($gwrap->getHex()->toString() === $gwrap->getCore()->getHex());
var_dump((string) $gwrap->getInteger() === $gwrap->getCore()->getInteger());
var_dump((new Guid($fixed)) instanceof \FastUuid\Compat\UuidInterface);
var_dump((new Guid($fixed))->getHex()->toString() === $fixed->getHex()->toString());
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
