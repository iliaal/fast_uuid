--TEST--
Compat generation accepts Ramsey-shaped Hexadecimal and Integer value objects
--EXTENSIONS--
fast_uuid
--FILE--
<?php
declare(strict_types=1);

require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Compat\Type\Integer;
use FastUuid\Compat\Uuid;

$node = new Hexadecimal('010203040506');

var_dump(substr(Uuid::uuid1($node, 1)->getBytes(), 10) === hex2bin((string) $node));
var_dump(substr(Uuid::uuid6($node, 2)->getBytes(), 10) === hex2bin((string) $node));
var_dump(substr(Uuid::fromDateTime(new DateTimeImmutable('@0'), $node, 3)->getBytes(), 10) === hex2bin((string) $node));

$v2 = Uuid::uuid2(Uuid::DCE_DOMAIN_PERSON, new Integer('42'), $node, 4);
var_dump($v2->getLocalIdentifier()->toString() === '42');
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
