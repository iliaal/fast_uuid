--TEST--
compat: UuidV2::getLocalIdentifier returns a Type\Integer; Hexadecimal rejects empty
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';
use FastUuid\Compat\Uuid;
use FastUuid\Compat\Type\Integer as IntegerObject;
use FastUuid\Compat\Type\Hexadecimal;

// getLocalIdentifier mirrors ramsey: an Integer object, not a bare int
$v2 = Uuid::uuid2(Uuid::DCE_DOMAIN_PERSON, 1234);
$id = $v2->getLocalIdentifier();
var_dump($id instanceof IntegerObject);
var_dump((string) $id === '1234');

// Hexadecimal rejects the empty string, like ramsey (ctype_xdigit('') is false)
try { new Hexadecimal(''); var_dump(false); }
catch (\FastUuid\Exception\InvalidArgumentException $e) { var_dump(true); }

// a real hex value still works, with the 0x prefix stripped
var_dump((string) new Hexadecimal('0xABCdef') === 'abcdef');
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
