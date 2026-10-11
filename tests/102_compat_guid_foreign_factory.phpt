--TEST--
Compat GUID restoration supports foreign factories without optional wrap()
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Guid\Guid;
use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Compat\Uuid;
use FastUuid\Compat\UuidFactory;
use FastUuid\Compat\UuidFactoryInterface;

$bare = new class implements UuidFactoryInterface {
    public function uuid1(int|string|Hexadecimal|null $node = null, ?int $clockSeq = null): \FastUuid\Compat\UuidInterface { throw new LogicException('unused'); }
    public function uuid2(int $localDomain, int|string|\FastUuid\Compat\Type\Integer|null $localIdentifier = null, int|string|Hexadecimal|null $node = null, ?int $clockSeq = null): \FastUuid\Compat\UuidInterface { throw new LogicException('unused'); }
    public function uuid3(\FastUuid\Compat\UuidInterface|string $ns, string $name): \FastUuid\Compat\UuidInterface { throw new LogicException('unused'); }
    public function uuid4(): \FastUuid\Compat\UuidInterface { throw new LogicException('unused'); }
    public function uuid5(\FastUuid\Compat\UuidInterface|string $ns, string $name): \FastUuid\Compat\UuidInterface { throw new LogicException('unused'); }
    public function uuid6(int|string|Hexadecimal|null $node = null, ?int $clockSeq = null): \FastUuid\Compat\UuidInterface { throw new LogicException('unused'); }
    public function fromString(string $uuid): \FastUuid\Compat\UuidInterface { throw new LogicException('unused'); }
    public function fromBytes(string $bytes): \FastUuid\Compat\UuidInterface { throw new LogicException('unused'); }
    public function fromInteger(string $integer): \FastUuid\Compat\UuidInterface { throw new LogicException('unused'); }
    public function fromDateTime(\DateTimeInterface $dateTime, int|string|Hexadecimal|null $node = null, ?int $clockSeq = null): \FastUuid\Compat\UuidInterface { throw new LogicException('unused'); }
    public function getValidator(): \FastUuid\Compat\Validator\ValidatorInterface { return new \FastUuid\Compat\Validator\NonstandardValidator(); }
};

$default = Uuid::getFactory();
$text = '00112233-4455-1677-8899-aabbccddeeff';
$core = \FastUuid\Uuid::fromString($text);
$guid = new Guid($default->fromString($text));
$serialized = serialize($guid);
Uuid::setFactory($bare);

// Native restoration must not call a foreign factory's presentation decoder.
foreach ([$core->getBytes(), $text] as $payload) {
    $restored = new Guid($default->uuid4());
    $restored->unserialize($payload);
    var_dump($restored->getCore()->equals($core));
    var_dump($restored->toString() === $text);
    var_dump(bin2hex($restored->getBytes()) === '33221100554477168899aabbccddeeff');
    $restored->__unserialize(['bytes' => $payload]);
    var_dump($restored->getCore()->equals($core));
}
$roundTrip = unserialize($serialized);
var_dump($roundTrip instanceof Guid && $roundTrip->getCore()->equals($core));

// Rejected restores leave an existing GUID's identity intact.
try {
    $roundTrip->unserialize('invalid');
    var_dump(false);
} catch (\FastUuid\Exception\InvalidUuidStringException) {
    var_dump($roundTrip->getCore()->equals($core));
}
Uuid::setFactory($default);
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
