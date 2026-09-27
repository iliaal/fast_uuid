--TEST--
Native UUID accessors preserve scalar union identity and skip unrelated static returns
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;
use FastUuid\UuidInterface;

abstract class ForeignUuid implements UuidInterface
{
    public const CORE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
    public function __construct(private string $text) {}
    public function __toString(): string { return $this->text; }
    public function jsonSerialize(): string { return $this->text; }
    protected function bytes(): string
    {
        return (string) hex2bin('6ba7b8109dad11d180b400c04fd430c8');
    }
}

final class NamedUnionBytes extends ForeignUuid
{
    public function getBytes(): string|Stringable { return $this->bytes(); }
}

final class ListUnionBytes extends ForeignUuid
{
    public function getBytes(): string|Stringable|Countable { return $this->bytes(); }
}

final class MixedBytes extends ForeignUuid
{
    public function getBytes(): mixed { return $this->bytes(); }
}

final class StaticCore extends ForeignUuid
{
    public function getCore(): static
    {
        throw new LogicException('unrelated static return must not run');
    }
}

final class NullableStaticCore extends ForeignUuid
{
    public function getCore(): ?static
    {
        throw new LogicException('unrelated nullable static return must not run');
    }
}

final class UnionStaticCore extends ForeignUuid
{
    public function getCore(): static|Uuid { return Uuid::fromString(self::CORE); }
}

final class SupertypeStaticCore extends ForeignUuid
{
    public function getCore(): static|Stringable { return Uuid::fromString(self::CORE); }
}

$text = '00112233-4455-4677-8899-aabbccddeeff';
$core = Uuid::fromString(ForeignUuid::CORE);
foreach ([
    new NamedUnionBytes($text),
    new ListUnionBytes($text),
    new MixedBytes($text),
    new StaticCore(ForeignUuid::CORE),
    new NullableStaticCore(ForeignUuid::CORE),
    new UnionStaticCore($text),
    new SupertypeStaticCore($text),
] as $namespace) {
    var_dump($core->equals($namespace));
    var_dump($core->compareTo($namespace) === 0);
    var_dump(Uuid::uuid3($namespace, 'www.example.com')->toString() === '5df41881-3aed-3515-88a7-2f4a814cf09e');
    var_dump(Uuid::uuid5($namespace, 'www.example.com')->toString() === '2ed6657d-e927-568b-95e1-2665a8aea6a2');
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
