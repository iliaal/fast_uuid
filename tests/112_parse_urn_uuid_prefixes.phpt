--TEST--
Parser and compat validators accept "urn:" and "uuid:" prefixes alone, as ramsey/uuid 4.10 does
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Validator\GenericValidator;
use FastUuid\Compat\Validator\NonstandardValidator;

$u = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
$generic = new GenericValidator();
$nonstandard = new NonstandardValidator();

foreach (["uuid:$u", "urn:$u", "UUID:$u", "URN:$u", "Urn:Uuid:$u", "urn:uuid:$u", "uuid:{" . $u . "}", "urn:{" . $u . "}",
          "{uuid:$u}", "{urn:$u}", 'uuid:' . str_replace('-', '', $u)] as $s) {
    var_dump(FastUuid\Uuid::isValid($s) && uuid_is_valid($s) && FastUuid\Uuid::fromString($s)->toString() === $u);
}
foreach (["uuid:$u", "urn:$u", "URN:$u", "uuid:{" . $u . "}"] as $s) {
    var_dump($generic->validate($s) && $nonstandard->validate($s));
}
// ramsey/uuid 4.10 takes the prefix only outside the braces.
var_dump(uuid_is_valid("{urn:$u}") && !$generic->validate("{urn:$u}") && !$nonstandard->validate("{urn:$u}"));
foreach (["uuid:urn:$u", "uuid$u", "urn$u", "urn: $u", "uid:$u", "uuid::$u", ":$u", "urn:urn:$u", "uuid:uuid:$u", "urn:{uuid:$u}", "uuid:{urn:$u}"] as $s) {
    var_dump(!FastUuid\Uuid::isValid($s) && !uuid_is_valid($s) && !$generic->validate($s) && !$nonstandard->validate($s));
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
