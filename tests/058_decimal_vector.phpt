--TEST--
Fixed decimal vector for a known v4, both directions (CR-025)
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;

// A fixed v4 vector (not a self-round-trip): the decimal form is pinned.
$v4 = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
$dec = '324969006592305634633390616021200786553';

var_dump(Uuid::fromString($v4)->getInteger() === $dec);
var_dump(Uuid::fromInteger($dec)->toString() === $v4);
var_dump(Uuid::fromInteger($dec)->getVersion() === 4);
var_dump(Uuid::fromString($v4)->getVariant() === 2);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
