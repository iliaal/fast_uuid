--TEST--
Native UUID resolution recursively accepts DNF getCore return types
--EXTENSIONS--
fast_uuid
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80200) die('skip DNF types require PHP 8.2');
?>
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Codec\StringCodec;
use FastUuid\Compat\Codec\TimestampFirstCombCodec;
use FastUuid\Compat\Guid\Guid;
use FastUuid\Compat\Rfc4122\Fields;
use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Compat\UuidFactory;
use FastUuid\Compat\UuidInterface as CompatUuidInterface;



require __DIR__ . '/../compat/src/Type/Integer.php';
require __DIR__ . '/../compat/src/Type/Hexadecimal.php';
require __DIR__ . '/../compat/src/Rfc4122/FieldsInterface.php';
$foreign = new class implements CompatUuidInterface
{
    public const CORE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
    private const TEXT = '00112233-4455-4677-8899-aabbccddeeff';

    public function getCore(): (Stringable&JsonSerializable)|string
    {
        return \FastUuid\Uuid::fromString(self::CORE);
    }
    public function toString(): string { return self::TEXT; }
    public function __toString(): string { return $this->toString(); }
    public function jsonSerialize(): string { return $this->toString(); }
    public function getBytes(): string { return (string) hex2bin(str_replace('-', '', self::TEXT)); }
    public function getFields(): \FastUuid\Compat\Rfc4122\FieldsInterface { return new Fields($this->getBytes()); }
    public function getHex(): \FastUuid\Compat\Type\Hexadecimal { return new Hexadecimal(str_replace('-', '', self::TEXT)); }
    public function getInteger(): \FastUuid\Compat\Type\Integer { return \FastUuid\Uuid::fromString(self::TEXT)->getInteger(); }
    public function getUrn(): string { return 'urn:uuid:' . self::TEXT; }
    public function getVariant(): int { return \FastUuid\Uuid::fromString(self::TEXT)->getVariant(); }
    public function getVersion(): ?int { return \FastUuid\Uuid::fromString(self::TEXT)->getVersion(); }
    public function getDateTime(): DateTimeInterface { return \FastUuid\Uuid::fromString(self::TEXT)->getDateTime(); }
    public function equals(mixed $other): bool { return \FastUuid\Uuid::fromString(self::TEXT)->equals($other); }
    public function compareTo(mixed $other): int { return \FastUuid\Uuid::fromString(self::TEXT)->compareTo($other); }
    public function serialize(): string { return self::TEXT; }
    public function unserialize(string $data): void {}
    public function __serialize(): array { return []; }
    public function __unserialize(array $data): void {}
};

$core = \FastUuid\Uuid::fromString($foreign::CORE);

$coreBytes = (string) hex2bin('6ba7b8109dad11d180b400c04fd430c8');
$name = 'www.example.com';
$expected3 = \FastUuid\Uuid::uuid3($core, $name)->toString();
$expected5 = \FastUuid\Uuid::uuid5($core, $name)->toString();
$factory = new UuidFactory();
$factory->setCodec(new TimestampFirstCombCodec());

var_dump($core->equals($foreign));
var_dump($core->compareTo($foreign) === 0);
var_dump((new StringCodec())->encodeBinary($foreign) === $coreBytes);
var_dump((new Guid($foreign))->getBytes() === (string) hex2bin('10b8a76bad9dd11180b400c04fd430c8'));
var_dump($factory->uuid3($foreign, $name)->getCore()->toString() === $expected3);
var_dump($factory->uuid5($foreign, $name)->getCore()->toString() === $expected5);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
