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

// InvalidUuidStringException parent chain.
$e = new InvalidUuidStringException();
var_dump($e instanceof FuInvalidArgument);
var_dump($e instanceof \InvalidArgumentException);
var_dump($e instanceof \Throwable);

// UnsupportedOperationException is a RuntimeException.
$u = new UnsupportedOperationException();
var_dump($u instanceof \RuntimeException);
var_dump($u instanceof \Throwable);

// uuid8 requires exactly 16 bytes; the extension throws plain \InvalidArgumentException.
$threw = false;
try { Uuid::uuid8('too short'); } catch (\InvalidArgumentException $x) { $threw = true; }
var_dump($threw);

// fromBytes requires exactly 16 bytes.
$threw = false;
try { Uuid::fromBytes('short'); } catch (\InvalidArgumentException $x) { $threw = true; }
var_dump($threw);

// The constructor is disabled: instantiation raises an Error.
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
