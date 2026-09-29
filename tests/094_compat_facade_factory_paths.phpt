--TEST--
compat: inlined facade and factory paths honour setFactory(), node providers and foreign factories
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Provider\NodeProviderInterface;
use FastUuid\Compat\Rfc4122\UuidV1;
use FastUuid\Compat\Rfc4122\UuidV7;
use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Compat\Uuid;
use FastUuid\Compat\UuidFactory;
use FastUuid\Compat\UuidFactoryInterface;
use FastUuid\Exception\UnsupportedOperationException;

// The default factory is created lazily and replaced wholesale by setFactory().
var_dump(Uuid::getFactory() instanceof UuidFactory);
var_dump(Uuid::getFactory() === Uuid::getFactory());
$default = Uuid::getFactory();
var_dump(Uuid::uuid4() instanceof \FastUuid\Compat\Rfc4122\UuidV4);

final class FixedNode implements NodeProviderInterface
{
    public function getNode(): string { return "\x01\x02\x03\x04\x05\x06"; }
}

// Every inlined facade method reaches the factory installed at call time.
final class CountingFactory extends UuidFactory
{
    /** @var array<string, int> */
    public array $calls = [];

    public function uuid1(int|string|Hexadecimal|null $node = null, ?int $clockSeq = null): \FastUuid\Compat\UuidInterface
    {
        $this->calls['uuid1'] = ($this->calls['uuid1'] ?? 0) + 1;
        return parent::uuid1($node, $clockSeq);
    }

    public function uuid3(\FastUuid\Compat\UuidInterface|string $ns, string $name): \FastUuid\Compat\UuidInterface
    {
        $this->calls['uuid3'] = ($this->calls['uuid3'] ?? 0) + 1;
        return parent::uuid3($ns, $name);
    }

    public function uuid4(): \FastUuid\Compat\UuidInterface
    {
        $this->calls['uuid4'] = ($this->calls['uuid4'] ?? 0) + 1;
        return parent::uuid4();
    }

    public function uuid5(\FastUuid\Compat\UuidInterface|string $ns, string $name): \FastUuid\Compat\UuidInterface
    {
        $this->calls['uuid5'] = ($this->calls['uuid5'] ?? 0) + 1;
        return parent::uuid5($ns, $name);
    }

    public function uuid6(int|string|Hexadecimal|null $node = null, ?int $clockSeq = null): \FastUuid\Compat\UuidInterface
    {
        $this->calls['uuid6'] = ($this->calls['uuid6'] ?? 0) + 1;
        return parent::uuid6($node, $clockSeq);
    }

    public function uuid7(int|\DateTimeInterface|null $dateTime = null): \FastUuid\Compat\UuidInterface
    {
        $this->calls['uuid7'] = ($this->calls['uuid7'] ?? 0) + 1;
        return parent::uuid7($dateTime);
    }

    public function fromString(string $uuid): \FastUuid\Compat\UuidInterface
    {
        $this->calls['fromString'] = ($this->calls['fromString'] ?? 0) + 1;
        return parent::fromString($uuid);
    }

    public function fromBytes(string $bytes): \FastUuid\Compat\UuidInterface
    {
        $this->calls['fromBytes'] = ($this->calls['fromBytes'] ?? 0) + 1;
        return parent::fromBytes($bytes);
    }

    public function getValidator(): \FastUuid\Compat\Validator\ValidatorInterface
    {
        $this->calls['getValidator'] = ($this->calls['getValidator'] ?? 0) + 1;
        return parent::getValidator();
    }
}

Uuid::uuid4();
$dnsNs = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
$counting = new CountingFactory();
Uuid::setFactory($counting);
Uuid::uuid1();
Uuid::uuid3($dnsNs, 'x');
Uuid::uuid4();
Uuid::uuid5($dnsNs, 'x');
Uuid::uuid6();
Uuid::uuid7();
Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
Uuid::fromBytes(str_repeat("\x11", 16));
Uuid::isValid('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
ksort($counting->calls);
var_dump($counting->calls === [
    'fromBytes' => 1, 'fromString' => 1, 'getValidator' => 1,
    'uuid1' => 1, 'uuid3' => 1, 'uuid4' => 1, 'uuid5' => 1, 'uuid6' => 1, 'uuid7' => 1,
]);
Uuid::setFactory($default);

$custom = new UuidFactory();
$custom->setNodeProvider(new FixedNode());
Uuid::setFactory($custom);
var_dump(Uuid::getFactory() === $custom);
foreach ([Uuid::uuid1(), Uuid::uuid6()] as $uuid) {
    var_dump($uuid->getFields()->getNode()->toString() === '010203040506');
}
// An explicit node beats the provider; Hexadecimal and int forms both convert.
var_dump(Uuid::uuid1(new Hexadecimal('0a0b0c0d0e0f'))->getFields()->getNode()->toString() === '0a0b0c0d0e0f');
var_dump(Uuid::uuid1('0a0b0c0d0e0f')->getFields()->getNode()->toString() === '0a0b0c0d0e0f');
// 0x0a0b0c0d0e0f is a float on 32-bit builds, so the int form only applies on 64-bit.
var_dump(PHP_INT_SIZE === 4 || Uuid::uuid1(0x0a0b0c0d0e0f)->getFields()->getNode()->toString() === '0a0b0c0d0e0f');
var_dump(Uuid::uuid6(new Hexadecimal('0a0b0c0d0e0f'))->getFields()->getNode()->toString() === '0a0b0c0d0e0f');

// With no provider set, no-argument generation still draws a random node (the
// multicast bit is set per RFC 9562) and never reuses one across calls.
Uuid::setFactory($default);
$nodes = [];
for ($i = 0; $i < 8; $i++) {
    $node = Uuid::uuid1()->getFields()->getNode()->toString();
    $nodes[$node] = (hexdec(substr($node, 0, 2)) & 1) === 1;
}
var_dump(count($nodes) > 1 && !in_array(false, $nodes, true));

// A foreign factory: uuid7()/uuid8() are reached only when it declares them.
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
Uuid::setFactory($bare);
try {
    Uuid::uuid7();
    var_dump(false);
} catch (UnsupportedOperationException) {
    var_dump(true);
}
// isValid() goes through the installed factory's validator, not the default one.
var_dump(Uuid::isValid('ffffffff-ffff-ffff-ffff-ffffffffffff'));

$withV7 = new class extends UuidFactory {
    public function uuid7(int|\DateTimeInterface|null $dateTime = null): \FastUuid\Compat\UuidInterface
    {
        return parent::uuid7(1700000000);
    }
};
Uuid::setFactory($withV7);
var_dump(Uuid::uuid7() instanceof UuidV7);
var_dump(Uuid::uuid7()->getCore()->getTimestampMillis() === 1700000000);

Uuid::setFactory($default);
var_dump(Uuid::isValid('ffffffff-ffff-ffff-ffff-ffffffffffff') === false);
var_dump(Uuid::uuid1() instanceof UuidV1);
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
