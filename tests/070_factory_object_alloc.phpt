--TEST--
Every factory returns a fresh, independent final Uuid object holding exactly its bytes
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;

$s = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
$bytes = (string) hex2bin(str_replace('-', '', $s));

$made = [
    'uuid1' => [Uuid::uuid1(), 1],
    'uuid2' => [Uuid::uuid2(Uuid::DCE_DOMAIN_PERSON, 1000), 2],
    'uuid3' => [Uuid::uuid3(Uuid::NAMESPACE_DNS, 'a'), 3],
    'uuid4' => [Uuid::uuid4(), 4],
    'uuid5' => [Uuid::uuid5(Uuid::NAMESPACE_DNS, 'a'), 5],
    'uuid6' => [Uuid::uuid6(), 6],
    'uuid7' => [Uuid::uuid7(), 7],
    'uuid8' => [Uuid::uuid8($bytes), 8],
    'fromDateTime' => [Uuid::fromDateTime(new DateTimeImmutable('2020-01-02 03:04:05')), 1],
];
$ok = true;
foreach ($made as [$u, $version]) {
    $ok = $ok && get_class($u) === Uuid::class && $u->getVersion() === $version
        && uuid_from_bin($u->getBytes()) === $u->toString();
}
var_dump($ok);

var_dump(Uuid::fromString($s)->getBytes() === $bytes);
var_dump(Uuid::fromBytes($bytes)->toString() === $s);
var_dump(Uuid::fromInteger(Uuid::fromString($s)->getInteger())->toString() === $s);
var_dump(Uuid::fromHexadecimal(str_replace('-', '', $s))->toString() === $s);
var_dump(Uuid::fromString(Uuid::NIL)->getBytes() === str_repeat("\x00", 16));
var_dump(Uuid::fromString(Uuid::MAX)->getBytes() === str_repeat("\xff", 16));

// Live objects are distinct instances with distinct values.
$live = [];
for ($i = 0; $i < 1000; $i++) {
    $live[] = Uuid::uuid4();
}
var_dump(count(array_unique(array_map('spl_object_id', $live))) === 1000);
var_dump(count(array_unique(array_map(static fn(Uuid $u): string => $u->toString(), $live))) === 1000);

// A recycled allocation carries no state from the object that held it before.
$a = Uuid::fromString($s);
$a->toString();
$a->getHex();
unset($a);
$b = Uuid::uuid4();
var_dump($b->getVersion() === 4 && $b->toString() !== $s && uuid_from_bin($b->getBytes()) === $b->toString());

// clone and serialization build through the same allocator.
$v = Uuid::fromString($s);
$c = clone $v;
var_dump($c !== $v && $c == $v && $c->equals($v) && $c->getBytes() === $bytes);
$r = unserialize(serialize($v));
var_dump($r !== $v && $r instanceof Uuid && $r->equals($v));

// The class is final and rejects dynamic properties.
var_dump((new ReflectionClass(Uuid::class))->isFinal());
$threw = false;
try { $v->extra = 1; } catch (Error) { $threw = true; }
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
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
