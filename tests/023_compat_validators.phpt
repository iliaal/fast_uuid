--TEST--
compat: validators (Generic, Nonstandard) and facade isValid
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Uuid;
use FastUuid\Compat\Validator\GenericValidator;
use FastUuid\Compat\Validator\NonstandardValidator;

$s = (string) Uuid::uuid4();
$bare = str_replace('-', '', $s);

var_dump((new GenericValidator())->validate($s));
var_dump((new GenericValidator())->validate(Uuid::NIL));
var_dump((new GenericValidator())->validate('nope') === false);
var_dump((new GenericValidator())->validate($bare) === false);

var_dump((new NonstandardValidator())->validate($s));

var_dump(Uuid::isValid($s) === true);
var_dump(Uuid::isValid($bare) === false);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
