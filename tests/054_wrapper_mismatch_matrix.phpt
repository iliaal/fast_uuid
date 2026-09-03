--TEST--
Wrapper version-binding guard over every wrapper class (CR-014)
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Guid\Guid;
use FastUuid\Compat\Internal\ConstructionToken;
use FastUuid\Compat\Nonstandard\Uuid as NonstandardUuid;
use FastUuid\Compat\Rfc4122\MaxUuid;
use FastUuid\Compat\Rfc4122\NilUuid;
use FastUuid\Compat\Rfc4122\UuidV1;
use FastUuid\Compat\Rfc4122\UuidV2;
use FastUuid\Compat\Rfc4122\UuidV3;
use FastUuid\Compat\Rfc4122\UuidV4;
use FastUuid\Compat\Rfc4122\UuidV5;
use FastUuid\Compat\Rfc4122\UuidV6;
use FastUuid\Compat\Rfc4122\UuidV7;
use FastUuid\Compat\Rfc4122\UuidV8;
use FastUuid\Exception\InvalidArgumentException;
use FastUuid\Uuid;

$cores = [
    'v1' => Uuid::uuid1(),
    'v2' => Uuid::uuid2(Uuid::DCE_DOMAIN_PERSON, 1),
    'v3' => Uuid::uuid3(Uuid::NAMESPACE_DNS, 'www.example.com'),
    'v4' => Uuid::uuid4(),
    'v5' => Uuid::uuid5(Uuid::NAMESPACE_DNS, 'www.example.com'),
    'v6' => Uuid::uuid6(),
    'v7' => Uuid::uuid7(new DateTimeImmutable('@1700000000')),
    'v8' => Uuid::uuid8(str_repeat("\0", 16)),
    'nil' => Uuid::fromString(Uuid::NIL),
    'max' => Uuid::fromString(Uuid::MAX),
    'ns' => Uuid::fromString('00000000-0000-1000-0000-000000000000'),
];

$map = [
    'v1' => UuidV1::class,
    'v2' => UuidV2::class,
    'v3' => UuidV3::class,
    'v4' => UuidV4::class,
    'v5' => UuidV5::class,
    'v6' => UuidV6::class,
    'v7' => UuidV7::class,
    'v8' => UuidV8::class,
    'nil' => NilUuid::class,
    'max' => MaxUuid::class,
    'ns' => NonstandardUuid::class,
];

function mismatchThrows(string $class, Uuid $core): bool {
    try {
        new $class($core, null, ConstructionToken::Trusted);
        return false;
    } catch (InvalidArgumentException) {
        return true;
    }
}

// Every wrapper accepts the core that resolves to its own class.
foreach ($map as $k => $class) {
    $w = new $class($cores[$k]);
    var_dump($w->getCore()->equals($cores[$k]));
}

// Every wrapper rejects cores that resolve to any other class, even with
// ConstructionToken::Trusted (the token buys nothing).
foreach ($map as $k => $class) {
    foreach ($cores as $j => $core) {
        if ($j === $k) {
            continue;
        }
        var_dump(mismatchThrows($class, $core));
    }
}

// Guid carries no version guard of its own: it delegates identity to the
// wrapped instance for every wrapper class.
foreach ($map as $k => $class) {
    $g = new Guid(new $class($cores[$k]));
    var_dump($g->getVersion() === $cores[$k]->getVersion());
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
