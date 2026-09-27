--TEST--
Foreign UUID resolution is codec-independent and ignores incompatible getCore arity
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Codec\StringCodec;
use FastUuid\Compat\Codec\TimestampFirstCombCodec;
use FastUuid\Compat\Guid\Guid;
use FastUuid\Compat\Rfc4122\Fields;
use FastUuid\Compat\Rfc4122\FieldsInterface;
use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Compat\Type\Integer as IntegerObject;
use FastUuid\Compat\Uuid;
use FastUuid\Compat\UuidFactory;
use FastUuid\Compat\UuidInterface;

class ForeignCanonicalUuid implements UuidInterface
{
    public function __construct(protected string $canonical) {}
    public function toString(): string { return $this->canonical; }
    public function __toString(): string { return $this->canonical; }
    public function jsonSerialize(): string { return $this->canonical; }
    public function getBytes(): string
    {
        return (string) hex2bin(str_replace('-', '', $this->canonical));
    }
    public function getHex(): Hexadecimal { return new Hexadecimal(str_replace('-', '', $this->canonical)); }
    public function getFields(): FieldsInterface { return new Fields($this->getBytes()); }
    public function getInteger(): IntegerObject { return Uuid::fromString($this->canonical)->getInteger(); }
    public function getUrn(): string { return 'urn:uuid:' . $this->canonical; }
    public function getVariant(): int { return Uuid::fromString($this->canonical)->getVariant(); }
    public function getVersion(): ?int { return Uuid::fromString($this->canonical)->getVersion(); }
    public function getDateTime(): DateTimeInterface { return Uuid::fromString($this->canonical)->getDateTime(); }
    public function equals(mixed $other): bool { return Uuid::fromString($this->canonical)->equals($other); }
    public function compareTo(mixed $other): int { return Uuid::fromString($this->canonical)->compareTo($other); }
    public function serialize(): string { return $this->canonical; }
    public function unserialize(string $data): void { $this->canonical = $data; }
    public function __serialize(): array { return ['canonical' => $this->canonical]; }
    public function __unserialize(array $data): void { $this->canonical = $data['canonical']; }
}
final class ForeignRequiredCoreUuid extends ForeignCanonicalUuid
{
    public function getCore(string $reason): FastUuid\Uuid
    {
        return FastUuid\Uuid::fromString($this->canonical);
    }
}
final class ForeignZeroCoreUuid extends ForeignCanonicalUuid
{
    public function getCore(): FastUuid\Uuid
    {
        return FastUuid\Uuid::fromString($this->canonical);
    }
}

final class ForeignStringCoreUuid extends ForeignCanonicalUuid
{
    public function getCore(): string
    {
        throw new LogicException('unrelated getCore method must not run');
    }
}

final class ForeignObjectCoreUuid extends ForeignCanonicalUuid
{
    public function getCore(): stdClass
    {
        throw new LogicException('unrelated class return must not run');
    }
}

final class ForeignCallableCoreUuid extends ForeignCanonicalUuid
{
    public function getCore(): callable
    {
        throw new LogicException('callable getCore method must not run');
    }
}


final class ForeignStringableCoreUuid extends ForeignCanonicalUuid
{
    public function getCore(): Stringable
    {
        return FastUuid\Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
    }
}

final class ForeignInterfaceCoreUuid extends ForeignCanonicalUuid
{
    public function getCore(): FastUuid\UuidInterface
    {
        return FastUuid\Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
    }
}

final class ForeignImpossibleIntersectionCoreUuid extends ForeignCanonicalUuid
{
    public function getCore(): Stringable&Countable
    {
        return FastUuid\Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
    }
}

$dns = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
$name = 'www.example.com';
$expected3 = '5df41881-3aed-3515-88a7-2f4a814cf09e';
$expected5 = '2ed6657d-e927-568b-95e1-2665a8aea6a2';
$canonicalWrapper = Uuid::fromString($dns);
$foreign = [new ForeignCanonicalUuid($dns), new ForeignRequiredCoreUuid($dns), new ForeignZeroCoreUuid($dns), new ForeignStringCoreUuid($dns), new ForeignObjectCoreUuid($dns), new ForeignImpossibleIntersectionCoreUuid($dns), new ForeignCallableCoreUuid($dns)];

foreach ($foreign as $namespace) {
    var_dump($canonicalWrapper->equals($namespace));
    var_dump($canonicalWrapper->compareTo($namespace) === 0);
    var_dump((new StringCodec())->encodeBinary($namespace) === hex2bin(str_replace('-', '', $dns)));
    var_dump((new Guid($namespace))->getBytes() === (string) hex2bin('10b8a76bad9dd11180b400c04fd430c8'));
}

$combFactory = new UuidFactory();
$combFactory->setCodec(new TimestampFirstCombCodec());
foreach ($foreign as $namespace) {
    var_dump($combFactory->uuid3($namespace, $name)->getCore()->toString() === $expected3);
    var_dump($combFactory->uuid5($namespace, $name)->getCore()->toString() === $expected5);
}

// Plain strings still follow the receiving factory's configured codec.
$codecText = (new TimestampFirstCombCodec())->encode($canonicalWrapper);
var_dump($combFactory->uuid3($codecText, $name)->getCore()->toString() === $expected3);
var_dump($combFactory->uuid5($codecText, $name)->getCore()->toString() === $expected5);

// The process-global facade delegates to the same safe factory path.
Uuid::setFactory($combFactory);
var_dump(Uuid::uuid3($foreign[1], $name)->getCore()->toString() === $expected3);

// Supertype declarations are legal core contracts: each accessor must run and
// expose its different network-order identity instead of the misleading text.
foreach ([new ForeignStringableCoreUuid('00112233-4455-4677-8899-aabbccddeeff'), new ForeignInterfaceCoreUuid('00112233-4455-4677-8899-aabbccddeeff')] as $namespace) {
    var_dump($canonicalWrapper->equals($namespace));
    var_dump($canonicalWrapper->compareTo($namespace) === 0);
    var_dump((new StringCodec())->encodeBinary($namespace) === (string) hex2bin('6ba7b8109dad11d180b400c04fd430c8'));
    var_dump((new Guid($namespace))->getBytes() === (string) hex2bin('10b8a76bad9dd11180b400c04fd430c8'));
    var_dump($combFactory->uuid3($namespace, $name)->getCore()->toString() === $expected3);
    var_dump($combFactory->uuid5($namespace, $name)->getCore()->toString() === $expected5);
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
bool(true)
bool(true)
bool(true)
