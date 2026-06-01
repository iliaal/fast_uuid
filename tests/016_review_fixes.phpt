--TEST--
uuid7 rejects pre-1970 dates; fromHexadecimal accepts a Stringable under strict_types
--EXTENSIONS--
fast_uuid
--FILE--
<?php
declare(strict_types=1);
use FastUuid\Uuid;

// uuid7 has no representation for timestamps before the unix epoch
try { Uuid::uuid7(new DateTimeImmutable('1969-12-31 23:59:59')); var_dump(false); }
catch (\FastUuid\Exception\InvalidArgumentException $e) { var_dump(true); }

// the epoch itself is fine
$z = Uuid::uuid7(new DateTimeImmutable('@0'));
var_dump($z->getVersion() === 7);
var_dump($z->getDateTime()->getTimestamp() === 0);

// fromHexadecimal accepts a plain 32-char string
$hex = '0a1b2c3d4e5f60718293a4b5c6d7e8f9';
var_dump(Uuid::fromHexadecimal($hex)->getHex() === $hex);

// and a Stringable object, even under strict_types (old stub said string-only)
$obj = new class($hex) implements \Stringable {
    public function __construct(private string $h) {}
    public function __toString(): string { return $this->h; }
};
var_dump(Uuid::fromHexadecimal($obj)->getHex() === $hex);

// a non-hex / wrong-length value is still rejected
try { Uuid::fromHexadecimal('xyz'); var_dump(false); }
catch (\FastUuid\Exception\InvalidUuidStringException $e) { var_dump(true); }
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
