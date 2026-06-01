--TEST--
compat: providers (custom RNG, custom NodeProvider) and factory defaults
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\UuidFactory;
use FastUuid\Compat\Provider\RandomGeneratorInterface;
use FastUuid\Compat\Provider\NodeProviderInterface;
use FastUuid\Compat\Provider\DefaultRandomGenerator;
use FastUuid\Compat\Validator\ValidatorInterface;
use FastUuid\Compat\Codec\CodecInterface;

$f = new UuidFactory();
$f->setRandomGenerator(new class implements RandomGeneratorInterface {
    public function generate(int $length): string { return str_repeat("\xAB", $length); }
});
$ru = $f->uuid4();
var_dump($ru->getVersion() === 4);
var_dump($ru->getVariant() === 2);

$exp = str_repeat("\xAB", 16);
$exp[6] = chr((ord($exp[6]) & 0x0f) | 0x40);
$exp[8] = chr((ord($exp[8]) & 0x3f) | 0x80);
var_dump($ru->getBytes() === $exp);

$f2 = new UuidFactory();
$node = "\x11\x22\x33\x44\x55\x66";
$f2->setNodeProvider(new class($node) implements NodeProviderInterface {
    public function __construct(private string $n) {}
    public function getNode(): string { return $this->n; }
});
$nu = $f2->uuid1();
var_dump(substr($nu->getBytes(), 10, 6) === $node);

var_dump((new UuidFactory())->getRandomGenerator() instanceof DefaultRandomGenerator);
var_dump((new UuidFactory())->getValidator() instanceof ValidatorInterface);
var_dump((new UuidFactory())->getCodec() instanceof CodecInterface);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
