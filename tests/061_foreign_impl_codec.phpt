--TEST--
Follow-up: codec encode paths accept third-party UuidInterface without getCore
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Codec\GuidStringCodec;
use FastUuid\Compat\Codec\OrderedTimeCodec;
use FastUuid\Compat\Codec\StringCodec;
use FastUuid\Compat\Codec\TimestampFirstCombCodec;
use FastUuid\Compat\Rfc4122\Fields;
use FastUuid\Compat\Rfc4122\FieldsInterface;
use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Compat\Type\Integer as IntegerObject;
use FastUuid\Compat\Uuid;
use FastUuid\Compat\UuidInterface;

// Third-party Ramsey-style implementation: no getCore() by design (CR-005).
final class ForeignUuid implements UuidInterface
{
    public function __construct(private string $canonical) {}
    public function toString(): string { return $this->canonical; }
    public function __toString(): string { return $this->canonical; }
    public function getBytes(): string { return \FastUuid\Uuid::fromString($this->canonical)->getBytes(); }
    public function compareTo(mixed $other): int { return \FastUuid\Uuid::fromString($this->canonical)->compareTo($other); }
    public function equals(mixed $other): bool { return \FastUuid\Uuid::fromString($this->canonical)->equals($other); }
    public function getHex(): Hexadecimal { return new Hexadecimal(\str_replace('-', '', $this->canonical)); }
    public function getFields(): FieldsInterface { return new Fields($this->getBytes()); }
    public function __serialize(): array { return ['s' => $this->canonical]; }
    public function __unserialize(array $data): void { $this->canonical = $data['s']; }
    public function getInteger(): IntegerObject { return Uuid::fromString($this->canonical)->getInteger(); }
    public function getUrn(): string { return 'urn:uuid:' . $this->canonical; }
    public function getVariant(): int { return \FastUuid\Uuid::fromString($this->canonical)->getVariant(); }
    public function getVersion(): ?int { return \FastUuid\Uuid::fromString($this->canonical)->getVersion(); }
    public function getDateTime(): \DateTimeInterface { return \FastUuid\Uuid::fromString($this->canonical)->getDateTime(); }
    public function jsonSerialize(): string { return $this->canonical; }
    public function serialize(): string { return ''; }
    public function unserialize(string $data): void {}
}

$v4 = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
$v1 = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
$w4 = Uuid::fromString($v4);
$f4 = new ForeignUuid($v4);
$w1 = Uuid::fromString($v1);
$f1 = new ForeignUuid($v1);

var_dump(!\method_exists($f4, 'getCore'));
$str = new StringCodec();
var_dump($str->encode($f4) === $str->encode($w4));
var_dump($str->encodeBinary($f4) === $str->encodeBinary($w4));
$guid = new GuidStringCodec();
var_dump($guid->encodeBinary($f4) === $guid->encodeBinary($w4));
$comb = new TimestampFirstCombCodec();
var_dump($comb->encodeBinary($f4) === $comb->encodeBinary($w4));
$ord = new OrderedTimeCodec();
var_dump($ord->encodeBinary($f1) === $ord->encodeBinary($w1));
// RV1: Guid over a foreign inner resolves via bytes, no getCore Error.
$g = new \FastUuid\Compat\Guid\Guid($f4);
var_dump($g->getBytes() === (new \FastUuid\Compat\Guid\Guid($w4))->getBytes());
var_dump($guid->encodeBinary($g) === $guid->encodeBinary($w4));
// RV2: non-Stringable object exposing getBytes() never resolves as UUID bytes.
$blob = new class($w4->getBytes()) { public function __construct(private string $b) {} public function getBytes(): string { return $this->b; } };
var_dump($w4->getCore()->equals($blob) === false);
// RV3: uuid3 garbage namespace preserves the InvalidUuidString subclass.
try {
    \FastUuid\Compat\Uuid::uuid3('not-a-uuid', 'name');
    var_dump(false);
} catch (\FastUuid\Exception\InvalidUuidStringException $e) {
    var_dump(true);
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
