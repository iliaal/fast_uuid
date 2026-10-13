--TEST--
Foreign GUID and ordered-time storage bytes do not override canonical UUID identity
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Uuid;
use FastUuid\Compat\Codec\GuidStringCodec;
use FastUuid\Compat\Codec\OrderedTimeCodec;
use FastUuid\Compat\Codec\TimestampFirstCombCodec;
use FastUuid\Compat\Rfc4122\Fields;
use FastUuid\Compat\Rfc4122\FieldsInterface;
use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Compat\Type\Integer;
use FastUuid\Compat\Uuid as CompatUuid;
use FastUuid\Compat\UuidFactory;
use FastUuid\Compat\UuidInterface;

// Independent foreign fixtures expose the storage orders used by Ramsey's codecs.
class ForeignStorageUuid implements Stringable
{
    public function __construct(private string $text, private string $storage) {}
    public function __toString(): string { return $this->text; }
    public function toString(): string { return $this->text; }
    public function getBytes(): string { return $this->storage; }
    public function getFields(): FieldsInterface { return new Fields(Uuid::fromString($this->text)->getBytes()); }
    public function getHex(): Hexadecimal { return new Hexadecimal(str_replace('-', '', $this->text)); }
    public function getInteger(): Integer { return new Integer(Uuid::fromString($this->text)->getInteger()); }
    public function getUrn(): string { return 'urn:uuid:' . $this->text; }
    public function getVersion(): ?int { return Uuid::fromString($this->text)->getVersion(); }
    public function getVariant(): int { return Uuid::fromString($this->text)->getVariant(); }
    public function getDateTime(): DateTimeInterface { return Uuid::fromString($this->text)->getDateTime(); }
    public function equals(mixed $other): bool { return Uuid::fromString($this->text)->equals($other); }
    public function compareTo(mixed $other): int { return Uuid::fromString($this->text)->compareTo($other); }
    public function jsonSerialize(): string { return $this->text; }
    public function serialize(): string { return $this->text; }
    public function unserialize(string $data): void { $this->text = $data; }
    public function __serialize(): array { return ['text' => $this->text, 'storage' => $this->storage]; }
    public function __unserialize(array $data): void { $this->text = $data['text']; $this->storage = $data['storage']; }
}

final class ForeignCompatStorageUuid extends ForeignStorageUuid implements UuidInterface {}

$dns = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
$core = Uuid::fromString($dns);
$compat = CompatUuid::fromString($dns);
$name = 'www.example.com';
$expected = [3 => '5df41881-3aed-3515-88a7-2f4a814cf09e', 5 => '2ed6657d-e927-568b-95e1-2665a8aea6a2'];
foreach (['10b8a76bad9dd11180b400c04fd430c8', '11d19dad6ba7b81080b400c04fd430c8'] as $storage) {
    $bytes = hex2bin($storage);
    $alias = Uuid::fromBytes($bytes);
    $compatAlias = CompatUuid::fromString($alias->toString());
    var_dump(!$core->equals($alias));
    foreach ([new ForeignStorageUuid($dns, $bytes), new ForeignCompatStorageUuid($dns, $bytes)] as $foreign) {
        var_dump($core->equals($foreign));
        var_dump($core->compareTo($foreign) === 0);
        var_dump($compat->equals($foreign));
        var_dump(!$alias->equals($foreign));
        var_dump(!$compatAlias->equals($foreign));
        var_dump($alias->compareTo($foreign) === $alias->compareTo($core));
        foreach ($expected as $version => $text) {
            $method = 'uuid' . $version;
            var_dump(Uuid::$method($foreign, $name)->toString() === $text);
            var_dump(CompatUuid::$method($foreign, $name)->toString() === $text);
        }
        if ($foreign instanceof UuidInterface) {
            var_dump($compat->compareTo($foreign) === 0);
            var_dump($compatAlias->compareTo($foreign) === $alias->compareTo($core));
        }
    }
}

foreach ([new GuidStringCodec(), new OrderedTimeCodec(), new TimestampFirstCombCodec()] as $codec) {
    $factory = new UuidFactory();
    $factory->setCodec($codec);
    $wrapper = $factory->wrap($core);
    var_dump($core->equals($wrapper));
    var_dump($core->compareTo($wrapper) === 0);
    var_dump($compat->equals($wrapper));
    var_dump($compat->compareTo($wrapper) === 0);
    foreach ($expected as $version => $text) {
        $method = 'uuid' . $version;
        var_dump(Uuid::$method($wrapper, $name)->toString() === $text);
        var_dump(CompatUuid::$method($wrapper, $name)->toString() === $text);
    }
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
