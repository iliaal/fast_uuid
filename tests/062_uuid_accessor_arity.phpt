--TEST--
Stringable UUID objects with required-argument accessors use canonical fallback
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;
use FastUuid\UuidInterface;

final class RequiredBytesUuid implements UuidInterface
{
    public function __construct(private string $canonical) {}
    public function __toString(): string { return $this->canonical; }
    public function jsonSerialize(): mixed { return $this->canonical; }
    public function getBytes(string $encoding): string
    {
        return (string) hex2bin(str_replace('-', '', $this->canonical));
    }
}

final class RequiredCoreUuid implements UuidInterface
{
    public function __construct(private string $canonical) {}
    public function __toString(): string { return $this->canonical; }
    public function jsonSerialize(): mixed { return $this->canonical; }
    public function getCore(string $reason): Uuid
    {
        return Uuid::fromString($this->canonical);
    }
}

final class IncompatibleCoreReturnUuid implements UuidInterface
{
    public function __construct(private string $canonical) {}
    public function __toString(): string { return $this->canonical; }
    public function jsonSerialize(): mixed { return $this->canonical; }
    public function getCore(): string
    {
        throw new LogicException('unrelated getCore method must not run');
    }
}

final class UnrelatedCoreResult {}
final class UnrelatedCoreReturnUuid implements UuidInterface
{
    public function __construct(private string $canonical) {}
    public function __toString(): string { return $this->canonical; }
    public function jsonSerialize(): mixed { return $this->canonical; }
    public function getCore(): UnrelatedCoreResult
    {
        throw new LogicException('unrelated class return must not run');
    }
}

final class CallableBytesUuid implements UuidInterface
{
    public function __construct(private string $canonical) {}
    public function __toString(): string { return $this->canonical; }
    public function jsonSerialize(): mixed { return $this->canonical; }
    public function getBytes(): callable
    {
        throw new LogicException('callable getBytes method must not run');
    }
}

$canonical = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
$marker = new class($canonical) implements UuidInterface {
    public function __construct(private string $canonical) {}
    public function __toString(): string { return $this->canonical; }
    public function jsonSerialize(): mixed { return $this->canonical; }
};
$rawAccessor = new class($canonical) implements UuidInterface {
    public function __construct(private string $canonical) {}
    public function __toString(): string { return $this->canonical; }
    public function jsonSerialize(): mixed { return $this->canonical; }
    public function getBytes(): string
    {
        return (string) hex2bin(str_replace('-', '', $this->canonical));
    }
};

$core = Uuid::fromString($canonical);
$expected3 = '5df41881-3aed-3515-88a7-2f4a814cf09e';
$expected5 = '2ed6657d-e927-568b-95e1-2665a8aea6a2';
foreach ([$marker, $rawAccessor, new RequiredBytesUuid($canonical), new RequiredCoreUuid($canonical), new IncompatibleCoreReturnUuid($canonical), new UnrelatedCoreReturnUuid($canonical), new CallableBytesUuid($canonical)] as $namespace) {
    var_dump($core->equals($namespace));
    var_dump($core->compareTo($namespace) === 0);
    var_dump(Uuid::uuid3($namespace, 'www.example.com')->toString() === $expected3);
    var_dump(Uuid::uuid5($namespace, 'www.example.com')->toString() === $expected5);
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
