--TEST--
compat: ramsey/uuid 4.x parity vectors (COMB codec, v2 fields, variant guard, clockSeq/v7 forms)
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Uuid;
use FastUuid\Compat\Codec\OrderedTimeCodec;
use FastUuid\Compat\Codec\TimestampFirstCombCodec;
use FastUuid\Compat\Nonstandard\Uuid as NonstandardUuid;
use FastUuid\Compat\Rfc4122\UuidV2;
use FastUuid\Exception\UnsupportedOperationException;

// TimestampFirstCombCodec: ramsey's swapBytes exchanges the first 6 and last
// 6 bytes, keeping bytes 6-9 in place. Fixed vector generated from
// ramsey/uuid 4.9.2.
$orig = Uuid::fromBytes(hex2bin('000102030405460788090a0b0c0d0e0f'));
$comb = new TimestampFirstCombCodec();
$enc = $comb->encodeBinary($orig);
var_dump(bin2hex($enc) === '0a0b0c0d0e0f46078809000102030405');
// Identity on the core: equals() follows presentation, and a decoded UUID
// carries the COMB codec (ramsey 4.9.2 behaves the same way).
var_dump($comb->decodeBytes($enc)->getCore()->equals($orig->getCore()));
var_dump($comb->decode($comb->encode($orig))->getCore()->equals($orig->getCore()));

// v2 fields: the local identifier (time_low) must not leak into the
// timestamp; ramsey and the C decoder both zero the low 32 bits.
$v2 = Uuid::fromBytes(hex2bin('000004d2abcd2ef08000aabbccddeeff'));
var_dump($v2 instanceof UuidV2);
var_dump($v2->getFields()->getTimestamp()->toString() === 'ef0abcd00000000');
var_dump($v2->getLocalIdentifier()->toString() === '1234');
var_dump($v2->getLocalDomain() === 0);
// Identifier >= 2^31 stays positive (32-bit unpack('N') signedness).
$v2big = Uuid::fromBytes(hex2bin('ffffffffabcd2ef08000aabbccddeeff'));
var_dump($v2big->getLocalIdentifier()->toString() === '4294967295');

// Fields::getVersion mirrors the C-layer variant guard: no version for
// non-RFC variants, and both surfaces agree.
$ms = Uuid::fromBytes(hex2bin('123456789abc4defc011aabbccddeeff'));
var_dump($ms instanceof NonstandardUuid);
var_dump($ms->getVersion() === null && $ms->getFields()->getVersion() === null);

// Max UUID clockSeq reports unmasked ffff (ramsey MaxTrait), nil stays 0000.
var_dump(Uuid::fromString(Uuid::MAX)->getFields()->getClockSeq()->toString() === 'ffff');
var_dump(Uuid::fromString(Uuid::NIL)->getFields()->getClockSeq()->toString() === '0000');

// v7 timestamp is zero-padded to 15 hex digits (60 bits) like ramsey.
$v7 = Uuid::uuid7(new DateTimeImmutable('@1700000000'));
var_dump($v7->getFields()->getTimestamp()->toString() === '000018bcfe56800');

// OrderedTimeCodec refuses to decode bytes that don't restore to version 1.
$threw = false;
try { (new OrderedTimeCodec())->decodeBytes(str_repeat("\x00", 16)); }
catch (UnsupportedOperationException) { $threw = true; }
var_dump($threw);

// Type value objects reject a trailing newline (previous ^$ anchoring let it
// through).
$threw = false;
try { new \FastUuid\Compat\Type\Hexadecimal("deadbeef\n"); }
catch (\FastUuid\Exception\InvalidArgumentException) { $threw = true; }
var_dump($threw);
$threw = false;
try { new \FastUuid\Compat\Type\Integer("123\n"); }
catch (\FastUuid\Exception\InvalidArgumentException) { $threw = true; }
var_dump($threw);
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
