--TEST--
compat: replacement factory, marker/type interfaces, validator pattern, and serialization parity
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Rfc4122\Fields;
use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Compat\Type\Integer as IntegerObject;
use FastUuid\Compat\Type\NumberInterface;
use FastUuid\Compat\Type\TypeInterface;
use FastUuid\Compat\Uuid;
use FastUuid\Compat\UuidFactory;
use FastUuid\Compat\UuidFactoryInterface;
use FastUuid\Compat\UuidInterface;
use FastUuid\Compat\Validator\GenericValidator;
use FastUuid\Compat\Validator\ValidatorInterface;
use FastUuid\Exception\UnsupportedOperationException;
use FastUuid\Exception\UuidExceptionInterface;

$methods = array_map(fn(ReflectionMethod $method) => $method->getName(), (new ReflectionClass(UuidFactoryInterface::class))->getMethods());
sort($methods);
$stableFactory = $methods === [
    'fromBytes', 'fromDateTime', 'fromInteger', 'fromString', 'getValidator',
    'uuid1', 'uuid2', 'uuid3', 'uuid4', 'uuid5', 'uuid6',
];
var_dump($stableFactory);

if ($stableFactory) {
    final class MinimalFactory implements UuidFactoryInterface
    {
        private UuidFactory $delegate;
        public function __construct() { $this->delegate = new UuidFactory(); }
        public function fromBytes(string $bytes): UuidInterface { return $this->delegate->fromBytes($bytes); }
        public function fromDateTime(DateTimeInterface $dateTime, int|string|Hexadecimal|null $node = null, ?int $clockSeq = null): UuidInterface { return $this->delegate->fromDateTime($dateTime, $node, $clockSeq); }
        public function fromInteger(string $integer): UuidInterface { return $this->delegate->fromInteger($integer); }
        public function fromString(string $uuid): UuidInterface { return $this->delegate->fromString($uuid); }
        public function getValidator(): ValidatorInterface { return $this->delegate->getValidator(); }
        public function uuid1(int|string|Hexadecimal|null $node = null, ?int $clockSeq = null): UuidInterface { return $this->delegate->uuid1($node, $clockSeq); }
        public function uuid2(int $localDomain, int|string|IntegerObject|null $localIdentifier = null, int|string|Hexadecimal|null $node = null, ?int $clockSeq = null): UuidInterface { return $this->delegate->uuid2($localDomain, $localIdentifier, $node, $clockSeq); }
        public function uuid3(UuidInterface|string $ns, string $name): UuidInterface { return $this->delegate->uuid3($ns, $name); }
        public function uuid4(): UuidInterface { return $this->delegate->uuid4(); }
        public function uuid5(UuidInterface|string $ns, string $name): UuidInterface { return $this->delegate->uuid5($ns, $name); }
        public function uuid6(int|string|Hexadecimal|null $node = null, ?int $clockSeq = null): UuidInterface { return $this->delegate->uuid6($node, $clockSeq); }
    }
}

if ($stableFactory) {
    Uuid::setFactory(new MinimalFactory());
    var_dump(Uuid::uuid4()->getVersion() === 4);
    try { Uuid::uuid7(); var_dump(false); } catch (Throwable $e) { var_dump($e::class === UnsupportedOperationException::class); }
    try { Uuid::uuid8(str_repeat("\0", 16)); var_dump(false); } catch (Throwable $e) { var_dump($e::class === UnsupportedOperationException::class); }
    try { Uuid::fromHexadecimal(new Hexadecimal(str_repeat('0', 32))); var_dump(false); } catch (Throwable $e) { var_dump($e::class === UnsupportedOperationException::class); }
} else {
    var_dump(false, false, false, false);
}

var_dump(interface_exists(UuidExceptionInterface::class));
var_dump(is_subclass_of(FastUuid\Exception\InvalidArgumentException::class, UuidExceptionInterface::class));
var_dump(is_subclass_of(FastUuid\Exception\InvalidUuidStringException::class, UuidExceptionInterface::class));
var_dump(is_subclass_of(UnsupportedOperationException::class, UuidExceptionInterface::class));

$hex = new Hexadecimal('0xABCD');
$integer = new IntegerObject('-0012');
var_dump($hex instanceof TypeInterface);
var_dump($integer instanceof NumberInterface);
var_dump(method_exists($hex, 'serialize') && $hex->serialize() === 'abcd');
var_dump(method_exists($integer, 'serialize') && $integer->serialize() === '-12');
if (method_exists($hex, 'unserialize') && method_exists($integer, 'unserialize')) {
    $hex->unserialize('1234');
    $integer->unserialize('99');
    var_dump($hex->toString() === '1234');
    var_dump($integer->toString() === '99');
} else {
    var_dump(false, false);
}

foreach ([UuidFactory::class, Fields::class, Hexadecimal::class, IntegerObject::class] as $class) {
    try {
        $object = match ($class) {
            UuidFactory::class => (new UuidFactory())->uuid4(),
            Fields::class => new Fields(str_repeat("\0", 16)),
            default => new $class('1'),
        };
        $object->__unserialize([]);
        var_dump(false);
    } catch (Throwable $e) {
        var_dump($e::class === ValueError::class);
    }
}

$validator = new GenericValidator();
$upper = 'AAAAAAAA-BBBB-4CCC-8DDD-EEEEEEEEEEEE';
var_dump($validator->validate($upper));
var_dump(preg_match('/' . $validator->getPattern() . '/Dms', $upper) === 1);
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
