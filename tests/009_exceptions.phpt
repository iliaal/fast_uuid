--TEST--
Exception hierarchy and disabled constructor / bad-length inputs throw
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;
use FastUuid\Exception\InvalidUuidStringException;
use FastUuid\Exception\InvalidArgumentException as FuInvalidArgument;
use FastUuid\Exception\UnsupportedOperationException;

$e = new InvalidUuidStringException();
var_dump($e instanceof FuInvalidArgument);
var_dump($e instanceof \InvalidArgumentException);
var_dump($e instanceof \Throwable);

$u = new UnsupportedOperationException();
var_dump($u instanceof \LogicException);
var_dump($u instanceof \Throwable);

$threw = false;
try { Uuid::uuid8('too short'); } catch (\Throwable $x) { $threw = $x::class === FuInvalidArgument::class; }
var_dump($threw);

$threw = false;
try { Uuid::fromBytes('short'); } catch (\Throwable $x) { $threw = $x::class === FuInvalidArgument::class; }
var_dump($threw);

$threw = false;
try { new Uuid(); } catch (\Error $x) { $threw = true; }
var_dump($threw);
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
