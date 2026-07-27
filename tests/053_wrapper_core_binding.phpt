--TEST--
Direct wrapper construction always validates its core (CR-003)
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

// Every wrapper validates its core against its own class on construction, and
// no argument a caller controls can turn that off. Guards CR-003, where
// ConstructionToken::Trusted skipped the check and was a public enum: this test
// fails on four lines if that skip comes back.

use FastUuid\Compat\Internal\ConstructionToken;
use FastUuid\Compat\Rfc4122\UuidV1;
use FastUuid\Compat\Rfc4122\UuidV4;
use FastUuid\Compat\Uuid;
use FastUuid\Compat\UuidFactory;
use FastUuid\Exception\InvalidArgumentException;

$factory = new UuidFactory();
$v1core = Uuid::uuid1()->getCore();
$v4core = Uuid::uuid4()->getCore();

function mismatchThrows(callable $c): bool {
    try { $c(); return false; } catch (InvalidArgumentException) { return true; }
}

// ConstructionToken::Trusted is a public enum, so it must not buy past the
// class/version check.
var_dump(mismatchThrows(fn() => new UuidV4($v1core, null, ConstructionToken::Trusted)));
var_dump(mismatchThrows(fn() => new UuidV1($v4core, null, ConstructionToken::Trusted)));

// Still true after a legitimate wrap has run through WrapperClass.
$wrapped = $factory->fromBytes($v1core->getBytes());
var_dump($wrapped instanceof UuidV1);
var_dump(mismatchThrows(fn() => new UuidV4($v1core, null, ConstructionToken::Trusted)));

// Matching class and core: accepted, with or without a token.
var_dump((new UuidV1($v1core))->getCore()->toString() === $v1core->toString());

// A distinct core object carrying identical bytes is treated on its own merits.
$twin = \FastUuid\Uuid::fromBytes($v1core->getBytes());
var_dump($twin->equals($v1core) && $twin !== $v1core);
var_dump(mismatchThrows(fn() => new UuidV4($twin, null, ConstructionToken::Trusted)));

// Every decode entry point still lands on the right wrapper.
var_dump($factory->fromString($v4core->toString()) instanceof UuidV4);
var_dump($factory->fromBytes($v4core->getBytes()) instanceof UuidV4);
var_dump($factory->wrap($v4core) instanceof UuidV4);
var_dump($factory->fromInteger($v4core->getInteger()) instanceof UuidV4);

// unserialize() validates on its own; a preceding wrap lends it nothing.
$payload = \str_replace(
    'FastUuid\Compat\Rfc4122\UuidV4',
    'FastUuid\Compat\Rfc4122\UuidV1',
    \serialize($factory->fromBytes($v4core->getBytes())),
);
var_dump(mismatchThrows(static fn() => \unserialize($payload)));
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
