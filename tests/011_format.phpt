--TEST--
fast_uuid: hex formatter correctness (lowercase, dash positions, urn)
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;

$ok = true;
for ($i = 0; $i < 500; $i++) {
    $u = Uuid::uuid4();
    $str = (string)$u;
    $hex = $u->getHex();
    $ok = $ok
        && $hex === bin2hex($u->getBytes())
        && $str === strtolower($str)
        && str_replace('-', '', $str) === $hex;
}
var_dump($ok);

$u = Uuid::uuid4();
$str = (string)$u;
var_dump($u->toString() === $u->__toString());
var_dump($u->getUrn() === 'urn:uuid:' . $u->toString());
var_dump($str[8] === '-');
var_dump($str[13] === '-');
var_dump($str[18] === '-');
var_dump($str[23] === '-');
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
