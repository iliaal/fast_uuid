--TEST--
Review fixes: presentation codec survives a serialize round trip (CR-021), procedural name cap (CR-023)
--EXTENSIONS--
fast_uuid
--INI--
memory_limit=256M
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Codec\GuidStringCodec;
use FastUuid\Compat\Codec\OrderedTimeCodec;
use FastUuid\Compat\Uuid;
use FastUuid\Compat\UuidFactory;
use FastUuid\Exception\InvalidArgumentException;

// --- OrderedTimeCodec: the BINARY(16) column keeps its byte order (CR-021) ---
$of = new UuidFactory();
$of->setCodec(new OrderedTimeCodec());
Uuid::setFactory($of);

$u = $of->uuid1();
$r = unserialize(serialize($u));
var_dump($u->getBytes() === $r->getBytes());
var_dump($u->toString() === $r->toString());
// still the ordered layout, not the network one
var_dump($u->getBytes() !== $u->getCore()->getBytes());
var_dump($r->getBytes() !== $r->getCore()->getBytes());
// and it still round-trips through the factory
var_dump($of->fromBytes($r->getBytes())->toString() === $u->toString());

// --- GuidStringCodec: presentation text is stable across the round trip ---
$gf = new UuidFactory();
$gf->setCodec(new GuidStringCodec());
Uuid::setFactory($gf);

$g = $gf->uuid4();
$gr = unserialize(serialize($g));
var_dump($g->toString() === $gr->toString());
var_dump($g->getCore()->getBytes() === $gr->getCore()->getBytes());

// --- default codec is unaffected ---
$df = new UuidFactory();
Uuid::setFactory($df);
$d = $df->uuid4();
$dr = unserialize(serialize($d));
var_dump($d->getBytes() === $dr->getBytes());
var_dump($dr->getBytes() === $dr->getCore()->getBytes());

// --- restore follows the active factory, and identity is codec-independent ---
Uuid::setFactory($gf);
$ser = serialize($gf->uuid4());
Uuid::setFactory($df);
$plain = unserialize($ser);
var_dump($plain->getBytes() === $plain->getCore()->getBytes());
var_dump(strlen($plain->serialize()) === 16);

// --- procedural v3/v5 honour the name cap and never return a value (CR-023) ---
$big = str_repeat('x', 16 * 1024 * 1024 + 1);
$ns  = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
foreach (['uuid_v3', 'uuid_v3_bin', 'uuid_v5', 'uuid_v5_bin'] as $fn) {
    $threw = false;
    try {
        $fn($ns, $big);
    } catch (InvalidArgumentException) {
        $threw = true;
    }
    var_dump($threw);
}
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
