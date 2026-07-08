--TEST--
Core contract regressions from review findings
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;
use FastUuid\Exception\InvalidArgumentException;
use FastUuid\Exception\InvalidUuidStringException;
use FastUuid\Exception\UnsupportedOperationException;

function throws(callable $fn, string $class): bool {
    try { $fn(); return false; } catch (Throwable $e) { return $e instanceof $class; }
}

$nonRfcV1 = Uuid::fromString('00000000-0000-1000-0000-000000000000');
var_dump($nonRfcV1->getVariant() === 0);
var_dump($nonRfcV1->getVersion() === null);
var_dump(throws(fn() => $nonRfcV1->getDateTime(), UnsupportedOperationException::class));
var_dump(throws(fn() => $nonRfcV1->getTimestampMillis(), UnsupportedOperationException::class));

$max = Uuid::fromString(Uuid::MAX);
var_dump(Uuid::fromInteger('0')->equals(Uuid::fromString(Uuid::NIL)));
var_dump(Uuid::fromInteger($max->getInteger())->equals($max));
var_dump(throws(fn() => Uuid::fromInteger('00'), InvalidArgumentException::class));
var_dump(throws(fn() => Uuid::fromInteger(str_repeat('0', 50)), InvalidArgumentException::class));
var_dump(throws(fn() => Uuid::fromInteger('340282366920938463463374607431768211456'), InvalidArgumentException::class));

var_dump(unpack('N', substr(Uuid::uuid2(Uuid::DCE_DOMAIN_PERSON, '4294967295')->getBytes(), 0, 4))[1] === 4294967295);
var_dump(throws(fn() => Uuid::uuid2(Uuid::DCE_DOMAIN_PERSON, '042'), InvalidArgumentException::class));
var_dump(throws(fn() => Uuid::uuid2(Uuid::DCE_DOMAIN_PERSON, str_repeat('0', 20)), InvalidArgumentException::class));
var_dump(throws(fn() => Uuid::uuid2(Uuid::DCE_DOMAIN_PERSON, '99999999999'), InvalidArgumentException::class));
var_dump(throws(fn() => Uuid::uuid2(-1, 1), InvalidArgumentException::class));
var_dump(throws(fn() => Uuid::uuid2(256, 1), InvalidArgumentException::class));
$domain255 = Uuid::uuid2(255, 1);
var_dump($domain255->getVersion() === 2 && ord($domain255->getBytes()[9]) === 255);

$badNodes = ['', '0102030405', '01020304050607', '01020304050g'];
$dt = new DateTimeImmutable('@0');
foreach ([
    fn(string $node) => Uuid::uuid1($node),
    fn(string $node) => Uuid::uuid6($node),
    fn(string $node) => Uuid::uuid2(Uuid::DCE_DOMAIN_PERSON, 1, $node),
    fn(string $node) => Uuid::fromDateTime($dt, $node),
] as $nodeUser) {
    $ok = true;
    foreach ($badNodes as $badNode) {
        $ok = $ok && throws(fn() => $nodeUser($badNode), InvalidArgumentException::class);
    }
    var_dump($ok);
}

var_dump(throws(fn() => uuid_to_bin('not-a-uuid'), InvalidUuidStringException::class));

$redacted = false;
try { Uuid::fromString('secret-token-not-a-uuid-with-extra'); }
catch (InvalidUuidStringException $e) { $redacted = !str_contains($e->getMessage(), 'secret-token'); }
var_dump($redacted);

var_dump(throws(fn() => uuid_v4_batch(100001), InvalidArgumentException::class));
var_dump(throws(fn() => fast_uuid_random_bytes(16 * 1024 * 1024 + 1), InvalidArgumentException::class));
var_dump(throws(fn() => uuid_from_bin(str_repeat("\0", 17)), InvalidArgumentException::class));
var_dump(throws(fn() => uuid_v8(''), InvalidArgumentException::class));
var_dump(throws(fn() => fast_uuid_random_bytes(-1), InvalidArgumentException::class));

$nilA = Uuid::fromString(Uuid::NIL);
$nilB = Uuid::fromBytes(str_repeat("\0", 16));
$maxObj = Uuid::fromString(Uuid::MAX);
var_dump($nilA == $nilB);
var_dump(($nilA <=> $maxObj) < 0);
var_dump(($maxObj <=> $nilA) > 0);
var_dump(($nilA <=> $nilB) === 0);

$variantType = (new ReflectionMethod(Uuid::class, 'getVariant'))->getReturnType();
var_dump($variantType instanceof ReflectionNamedType && $variantType->getName() === 'int' && !$variantType->allowsNull());
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
