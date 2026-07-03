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
$upper = strtoupper($s);
$urn = 'urn:uuid:' . $s;
$braced = '{' . $s . '}';
$futureVariant = 'a1b2c3d4-e5f6-4718-f93a-4b5c6d7e8f90';

var_dump((new GenericValidator())->validate($s));
var_dump((new GenericValidator())->validate($upper));
var_dump((new GenericValidator())->validate($urn));
var_dump((new GenericValidator())->validate($braced));
var_dump((new GenericValidator())->validate(Uuid::NIL));
var_dump((new GenericValidator())->validate('nope') === false);
var_dump((new GenericValidator())->validate($bare) === false);
var_dump((new GenericValidator())->validate('a1b2c3d4-e5f6-4718-893a-4b5c6d7e8fg0') === false);
var_dump((new GenericValidator())->validate($futureVariant));

var_dump((new NonstandardValidator())->validate($s));
var_dump((new NonstandardValidator())->validate($upper));
var_dump((new NonstandardValidator())->validate($urn));
var_dump((new NonstandardValidator())->validate($bare) === false);
var_dump((new NonstandardValidator())->validate($futureVariant));

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
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
