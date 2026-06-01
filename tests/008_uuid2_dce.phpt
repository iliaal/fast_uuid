--TEST--
uuid2 DCE Security: version 2, variant 2, domain byte and local identifier
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;

// Auto-filled identifier (PERSON domain) is still version 2, variant 2.
$p = Uuid::uuid2(Uuid::DCE_DOMAIN_PERSON);
var_dump($p->getVersion() === 2);
var_dump($p->getVariant() === 2);

// Explicit local identifier, GROUP domain.
$g = Uuid::uuid2(Uuid::DCE_DOMAIN_GROUP, 4242);
var_dump($g->getVersion() === 2);
var_dump($g->getVariant() === 2);

$bytes = $g->getBytes();

// Byte 9 carries the local domain (GROUP == 1).
var_dump($bytes[9] === chr(Uuid::DCE_DOMAIN_GROUP));

// Bytes 0..3 carry the local identifier, big-endian.
var_dump(unpack('N', substr($bytes, 0, 4))[1] === 4242);

// getDateTime() is coarse but must not throw and yields an immutable.
var_dump($g->getDateTime() instanceof \DateTimeImmutable);

// Repeated calls with the same args stay version 2.
$a = Uuid::uuid2(Uuid::DCE_DOMAIN_ORG, 7);
$b = Uuid::uuid2(Uuid::DCE_DOMAIN_ORG, 7);
var_dump($a->getVersion() === 2);
var_dump($b->getVersion() === 2);
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
