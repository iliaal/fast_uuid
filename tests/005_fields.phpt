--TEST--
Uuid::getFields() returns RFC 4122 fields that reassemble into the hex form
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;

$u = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
$f = $u->getFields();

// Exact key set and order.
var_dump(array_keys($f) === [
    'time_low',
    'time_mid',
    'time_hi_and_version',
    'clock_seq_hi_and_reserved',
    'clock_seq_low',
    'node',
]);

// Individual field values for this fixed UUID.
var_dump($f['time_low'] === '6ba7b810');
var_dump($f['time_mid'] === '9dad');
var_dump($f['time_hi_and_version'] === '11d1');
var_dump($f['clock_seq_hi_and_reserved'] === '80');
var_dump($f['clock_seq_low'] === 'b4');
var_dump($f['node'] === '00c04fd430c8');

// Concatenating the fields in canonical order reproduces getHex().
$concat = $f['time_low']
    . $f['time_mid']
    . $f['time_hi_and_version']
    . $f['clock_seq_hi_and_reserved']
    . $f['clock_seq_low']
    . $f['node'];
var_dump($concat === $u->getHex());
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
