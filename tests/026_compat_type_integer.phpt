--TEST--
compat: Type\Integer sign normalization and factory short-generator guard
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';
use FastUuid\Compat\Type\Integer;
use FastUuid\Compat\UuidFactory;
use FastUuid\Compat\Provider\RandomGeneratorInterface;

// negative zero normalizes to "0", not "-"
var_dump((string) new Integer('-0') === '0');
var_dump((string) new Integer('0') === '0');
var_dump((string) new Integer('007') === '7');
var_dump((string) new Integer('-007') === '-7');
var_dump((string) new Integer('42') === '42');
var_dump((string) new Integer(-42) === '-42');

// a random generator returning the wrong length is rejected up front
$f = new UuidFactory();
$f->setRandomGenerator(new class implements RandomGeneratorInterface {
    public function generate(int $length): string { return "\x00\x00\x00"; }
});
try { $f->uuid4(); var_dump(false); }
catch (\FastUuid\Exception\InvalidArgumentException $e) { var_dump(true); }
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
