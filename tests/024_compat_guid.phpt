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
// decodeBytes() takes GUID-ordered bytes, so it round-trips getBytes().
var_dump((new GuidStringCodec())->decodeBytes($gbytes)->equals($u4));
// Only the byte array is mixed-endian; the text is the plain RFC form.
var_dump($g->toString() === $u4->toString());
var_dump($g->getUrn() === 'urn:uuid:' . $g->toString());

$fixed = Uuid::fromString('00112233-4455-4677-8899-aabbccddeeff');
$codec = new GuidStringCodec();
$factory = new UuidFactory();
$factory->setCodec($codec);
var_dump($factory->fromString($codec->encode($fixed))->equals($fixed));
var_dump($factory->fromBytes($codec->encodeBinary($fixed))->equals($fixed));
// The Guid codec reshapes bytes only, so its text is the canonical RFC form and
// every derived identity follows it (ramsey 4.9.2 parity).
var_dump($codec->encode($fixed) === $fixed->toString());
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

// fu-o2o: serialize() writes network-order bytes; restore must not re-swap
// them even when the process-global factory codec is GuidStringCodec.
$guid = new Guid(Uuid::uuid4());
$payload = serialize($guid);
$guidFactory = new UuidFactory();
$guidFactory->setCodec(new GuidStringCodec());
Uuid::setFactory($guidFactory);
$restored = unserialize($payload);
var_dump($restored->equals($guid));
var_dump($restored->getUuid()->getCore()->toString() === $guid->getUuid()->getCore()->toString());
var_dump($restored->getBytes() === $guid->getBytes());
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
bool(true)
bool(true)
bool(true)
