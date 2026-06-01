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

$u4 = Uuid::uuid4();
$g = new Guid($u4);
$raw = $u4->getBytes();
$gbytes = $g->getBytes();

var_dump($gbytes[0] === $raw[3] && $gbytes[3] === $raw[0] && substr($gbytes, 8) === substr($raw, 8));
var_dump((new GuidStringCodec())->decodeBytes($gbytes)->equals($u4));
var_dump(strlen($g->toString()) === 36);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
