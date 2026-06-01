--TEST--
compat: codecs (OrderedTime, TimestampFirstComb, TimestampLastComb, String)
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Uuid;
use FastUuid\Compat\Codec\StringCodec;
use FastUuid\Compat\Codec\OrderedTimeCodec;
use FastUuid\Compat\Codec\TimestampFirstCombCodec;
use FastUuid\Compat\Codec\TimestampLastCombCodec;
use FastUuid\Exception\InvalidArgumentException;

$u1 = Uuid::uuid1();
$ot = new OrderedTimeCodec();
$enc = $ot->encodeBinary($u1);
var_dump($enc !== $u1->getBytes() && strlen($enc) === 16);
var_dump($ot->decodeBytes($enc)->equals($u1));

$threw = false;
try { $ot->encodeBinary(Uuid::uuid4()); } catch (InvalidArgumentException) { $threw = true; }
var_dump($threw);

$u4 = Uuid::uuid4();
$tf = new TimestampFirstCombCodec();
var_dump($tf->decodeBytes($tf->encodeBinary($u4))->equals($u4));
var_dump($tf->decode($tf->encode($u4))->equals($u4));
var_dump($tf->encodeBinary($u4) !== $u4->getBytes());

$tl = new TimestampLastCombCodec();
var_dump($tl->decode($tl->encode($u4))->equals($u4));

$sc = new StringCodec();
var_dump($sc->decode($sc->encode($u4))->equals($u4));
var_dump($sc->decodeBytes($sc->encodeBinary($u4))->equals($u4));
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
