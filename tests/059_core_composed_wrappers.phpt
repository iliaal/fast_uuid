--TEST--
Core fromString/isValid on composed wrapper forms (CR-026)
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Exception\InvalidUuidStringException;
use FastUuid\Uuid;

function coreRejects(string $s): bool {
    try {
        Uuid::fromString($s);
        return false;
    } catch (InvalidUuidStringException) {
        return true;
    }
}

$c = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
$canon = Uuid::fromString($c);

// The C parser strips urn:uuid: prefixes and braces (in either order).
foreach (['urn:uuid:' . $c, 'urn:uuid:{' . $c . '}', '{urn:uuid:' . $c . '}'] as $form) {
    var_dump(Uuid::isValid($form));
    var_dump(Uuid::fromString($form)->equals($canon));
}

// Composed wrappers of garbage stay rejected at the core layer too.
var_dump(Uuid::isValid('{urn:uuid:nope}') === false);
var_dump(coreRejects('{urn:uuid:nope}'));
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
