--TEST--
v8 success path (core, procedural, compat) and procedural uuid_v3/uuid_v5
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Uuid;
use FastUuid\Compat\Uuid as CompatUuid;
use FastUuid\Compat\Rfc4122\UuidV8;

// v8: version/variant nibbles forced, every other bit preserved verbatim.
$bytes = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f";
$u = Uuid::uuid8($bytes);
var_dump($u->getVersion() === 8);
var_dump($u->getVariant() === 2);
var_dump($u->toString() === '00010203-0405-8607-8809-0a0b0c0d0e0f');
var_dump(Uuid::fromString($u->toString())->getVersion() === 8);

// Procedural form produces the identical layout.
var_dump(uuid_v8($bytes) === '00010203-0405-8607-8809-0a0b0c0d0e0f');
var_dump(uuid_is_valid(uuid_v8(random_bytes(16))));

// Compat mapping: v8 wraps as UuidV8.
$c = CompatUuid::uuid8($bytes);
var_dump($c instanceof UuidV8);
var_dump($c->getVersion() === 8);

// Procedural name-based generators: deterministic, RFC 9562 test vectors,
// and identical to the OO factories.
var_dump(uuid_v3(Uuid::NAMESPACE_DNS, 'www.example.com') === '5df41881-3aed-3515-88a7-2f4a814cf09e');
var_dump(uuid_v5(Uuid::NAMESPACE_DNS, 'www.example.com') === '2ed6657d-e927-568b-95e1-2665a8aea6a2');
var_dump(uuid_v3(Uuid::NAMESPACE_DNS, 'php.net') === Uuid::uuid3(Uuid::NAMESPACE_DNS, 'php.net')->toString());
var_dump(uuid_v5(Uuid::NAMESPACE_DNS, 'php.net') === Uuid::uuid5(Uuid::NAMESPACE_DNS, 'php.net')->toString());

// Invalid namespace is rejected on the procedural path too.
$threw = false;
try { uuid_v3('not-a-namespace', 'x'); } catch (\FastUuid\Exception\InvalidArgumentException) { $threw = true; }
var_dump($threw);
$threw = false;
try { uuid_v5('not-a-namespace', 'x'); } catch (\FastUuid\Exception\InvalidArgumentException) { $threw = true; }
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
