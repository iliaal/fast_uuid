--TEST--
Compat v3/v5 namespaces retain canonical identity independently of the output codec
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Codec\GuidStringCodec;
use FastUuid\Compat\Codec\OrderedTimeCodec;
use FastUuid\Compat\Codec\StringCodec;
use FastUuid\Compat\Codec\TimestampFirstCombCodec;
use FastUuid\Compat\Codec\TimestampLastCombCodec;
use FastUuid\Compat\Guid\Guid;
use FastUuid\Compat\Rfc4122\FieldsInterface;
use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Compat\Type\Integer as IntegerObject;
use FastUuid\Compat\Uuid;
use FastUuid\Compat\UuidFactory;
use FastUuid\Compat\UuidInterface;

final class ForeignNamespace implements UuidInterface
{
    public function __construct(private UuidInterface $uuid) {}
    public function toString(): string { return $this->uuid->toString(); }
    public function __toString(): string { return $this->toString(); }
    public function getBytes(): string { return $this->uuid->getBytes(); }
    public function compareTo(mixed $other): int { return $this->uuid->compareTo($other); }
    public function equals(mixed $other): bool { return $this->uuid->equals($other); }
    public function getHex(): Hexadecimal { return $this->uuid->getHex(); }
    public function getFields(): FieldsInterface { return $this->uuid->getFields(); }
    public function getInteger(): IntegerObject { return $this->uuid->getInteger(); }
    public function getUrn(): string { return $this->uuid->getUrn(); }
    public function getVariant(): int { return $this->uuid->getVariant(); }
    public function getVersion(): ?int { return $this->uuid->getVersion(); }
    public function getDateTime(): \DateTimeInterface { return $this->uuid->getDateTime(); }
    public function jsonSerialize(): string { return $this->toString(); }
    public function serialize(): string { return $this->uuid->serialize(); }
    public function unserialize(string $data): void { $this->uuid->unserialize($data); }
    public function __serialize(): array { return ['uuid' => $this->uuid]; }
    public function __unserialize(array $data): void { $this->uuid = $data['uuid']; }
}

final class InheritedFactory extends UuidFactory {}

final class CombCodecFactory extends UuidFactory
{
    public function getCodec(): \FastUuid\Compat\Codec\CodecInterface
    {
        return new TimestampFirstCombCodec();
    }
}

final class NamespaceAliasFactory extends UuidFactory
{
    public function fromString(string $uuid): UuidInterface
    {
        return (new UuidFactory())->fromString($uuid === 'dns' ? Uuid::NAMESPACE_DNS : $uuid);
    }
}

$dns = Uuid::NAMESPACE_DNS;
$default = new UuidFactory();
$foreign = new ForeignNamespace($default->fromString($dns));
var_dump(!method_exists($foreign, 'getCore'));
$vectors = [
    'uuid3' => '5df41881-3aed-3515-88a7-2f4a814cf09e',
    'uuid5' => '2ed6657d-e927-568b-95e1-2665a8aea6a2',
];
$swappedVectors = [
    'uuid3' => 'e00d3003-ab86-3481-a522-eae05990f449',
    'uuid5' => 'aa048f40-e6ad-5059-887d-7db3edb58ec8',
];
$savedFactory = Uuid::getFactory();
try {
    foreach ([new StringCodec(), new GuidStringCodec(), new TimestampFirstCombCodec(),
        new TimestampLastCombCodec(), new OrderedTimeCodec()] as $codec) {
        $factory = new UuidFactory();
        $factory->setCodec($codec);
        Uuid::setFactory($factory);
        $namespaces = [$dns, $foreign, $factory->wrap(\FastUuid\Uuid::fromString($dns)),
            new Guid($default->fromString($dns))];
        $identity = true;
        $presentation = true;
        foreach ($namespaces as $namespace) {
            foreach ($vectors as $method => $expected) {
                foreach ([$factory->$method($namespace, 'www.example.com'),
                    Uuid::$method($namespace, 'www.example.com')] as $uuid) {
                    $identity = ($uuid->getCore()->toString() === $expected) && $identity;
                    $presentation = ($uuid->toString() === $codec->encode($default->fromString($expected)))
                        && $presentation;
                }
            }
        }
        var_dump($identity);
        var_dump($presentation);

        foreach ($swappedVectors as $method => $expected) {
            $uuid = $factory->$method('00c04fd4-30c8-11d1-80b4-6ba7b8109dad', 'www.example.com');
            var_dump($uuid->getCore()->toString() === $expected);
        }
    }
} finally {
    Uuid::setFactory($savedFactory);
}

$inherited = new InheritedFactory();
$inherited->setCodec(new TimestampFirstCombCodec());
$overriding = new CombCodecFactory();
$overriding->setCodec(new TimestampFirstCombCodec());
$aliased = new NamespaceAliasFactory();
$aliased->setCodec(new TimestampFirstCombCodec());
foreach ($vectors as $method => $expected) {
    var_dump($inherited->$method($dns, 'www.example.com')->getCore()->toString() === $expected);
    var_dump($overriding->$method($dns, 'www.example.com')->getCore()->toString() === $expected);
    var_dump($aliased->$method('dns', 'www.example.com')->getCore()->toString() === $expected);
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
