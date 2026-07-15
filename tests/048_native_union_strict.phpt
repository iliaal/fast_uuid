--TEST--
native unions enforce TypeError for invalid strict scalar types
--EXTENSIONS--
fast_uuid
--FILE--
<?php
declare(strict_types=1);

use FastUuid\Uuid;

function throwsType(callable $call): bool {
    try { $call(); return false; } catch (Throwable $e) { return $e::class === TypeError::class; }
}

var_dump(throwsType(fn() => Uuid::uuid1(true)));
var_dump(throwsType(fn() => Uuid::uuid2(0, true)));
var_dump(throwsType(fn() => Uuid::uuid2(0, 1, true)));
var_dump(throwsType(fn() => Uuid::uuid6(1.5)));
var_dump(throwsType(fn() => Uuid::fromDateTime(new DateTimeImmutable('@0'), [])));
var_dump(throwsType(fn() => Uuid::fromHexadecimal(123)));
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
