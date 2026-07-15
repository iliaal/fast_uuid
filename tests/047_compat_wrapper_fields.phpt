--TEST--
compat: wrapper constructors and fields enforce their concrete contracts
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Nonstandard\Fields as NonstandardFields;
use FastUuid\Compat\Rfc4122\Fields;
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
use FastUuid\Compat\Uuid;
use FastUuid\Exception\InvalidArgumentException;

function throwsInvalid(callable $call): bool {
    try { $call(); return false; } catch (Throwable $e) { return $e::class === InvalidArgumentException::class; }
}

$cores = [
    UuidV1::class => FastUuid\Uuid::uuid1(),
    UuidV2::class => FastUuid\Uuid::uuid2(FastUuid\Uuid::DCE_DOMAIN_PERSON, 1),
    UuidV3::class => FastUuid\Uuid::uuid3(FastUuid\Uuid::NAMESPACE_DNS, 'example.com'),
    UuidV4::class => FastUuid\Uuid::uuid4(),
    UuidV5::class => FastUuid\Uuid::uuid5(FastUuid\Uuid::NAMESPACE_DNS, 'example.com'),
    UuidV6::class => FastUuid\Uuid::uuid6(),
    UuidV7::class => FastUuid\Uuid::uuid7(0),
    UuidV8::class => FastUuid\Uuid::uuid8(str_repeat("\0", 16)),
    NilUuid::class => FastUuid\Uuid::fromString(FastUuid\Uuid::NIL),
    MaxUuid::class => FastUuid\Uuid::fromString(FastUuid\Uuid::MAX),
];

foreach ($cores as $class => $core) {
    var_dump((new $class($core)) instanceof $class);
    $wrong = $class === UuidV4::class ? $cores[UuidV1::class] : $cores[UuidV4::class];
    var_dump(throwsInvalid(fn() => new $class($wrong)));
}

$v4 = Uuid::fromString('00010203-0405-4607-8809-0a0b0c0d0e0f');
try {
    var_dump((string) $v4->getFields()->getTimestamp() === '607040500010203');
} catch (Throwable) {
    var_dump(false);
}

$nonRfcBytes = hex2bin('00000000000010000000000000000000');
var_dump(throwsInvalid(fn() => new Fields($nonRfcBytes)));
$nonRfc = Uuid::fromString('00000000-0000-1000-0000-000000000000');
var_dump($nonRfc->getFields() instanceof NonstandardFields);
try {
    var_dump((string) $nonRfc->getFields()->getTimestamp() === '000000000000000');
} catch (Throwable) {
    var_dump(false);
}
var_dump((new Fields(str_repeat("\0", 16)))->isNil());
var_dump((new Fields(str_repeat("\xff", 16)))->isMax());
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
