--TEST--
compat: AbstractUuid's inheritable surface carries no minting helpers, so subclasses may declare statics with those names
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\AbstractUuid;
use FastUuid\Compat\Internal\Minter;

// Public and protected methods as of 2bd5362, before the minting helpers.
$expected = [
    '__construct', '__serialize', '__toString', '__unserialize', 'compareTo',
    'equals', 'getBytes', 'getCore', 'getDateTime', 'getFields', 'getHex',
    'getInteger', 'getUrn', 'getVariant', 'getVersion', 'jsonSerialize',
    'serialize', 'toString', 'unserialize',
];
$r = new ReflectionClass(AbstractUuid::class);
$actual = [];
foreach ($r->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED) as $m) {
    $actual[] = $m->getName();
    if (!$m->isPublic() || $m->isStatic() || $m->class !== AbstractUuid::class) {
        echo "unexpected shape: {$m->class}::{$m->getName()}\n";
    }
}
sort($actual);
var_dump($actual === $expected);

$props = array_map(static fn(ReflectionProperty $p): string => $p->getName(), $r->getProperties());
sort($props);
var_dump($props === ['codec', 'core']);
var_dump($r->getProperty('core')->isProtected() && $r->getProperty('codec')->isProtected());

// Each of these clashed with a public static on AbstractUuid and failed at class load.
abstract class UserUuid extends AbstractUuid
{
    public static function mintV4(string $tag): string { return "v4:$tag"; }
    public function wrapCore(): string { return 'instance'; }
    protected static function mintFromDateTime(): string { return 'protected'; }
    public static function mintV7(): int { return 7; }
}
var_dump(UserUuid::mintV4('x') === 'v4:x' && UserUuid::mintV7() === 7);

$m = new ReflectionClass(Minter::class);
var_dump($m->isFinal() && !$m->isInstantiable());

// The helpers keep building the in-tree wrapper for every version.
$ok = true;
foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $v) {
    $core = match ($v) {
        1 => \FastUuid\Uuid::uuid1(),
        2 => \FastUuid\Uuid::uuid2(0, 1),
        3 => \FastUuid\Uuid::uuid3(\FastUuid\Uuid::NAMESPACE_DNS, 'a'),
        4 => \FastUuid\Uuid::uuid4(),
        5 => \FastUuid\Uuid::uuid5(\FastUuid\Uuid::NAMESPACE_DNS, 'a'),
        6 => \FastUuid\Uuid::uuid6(),
        7 => \FastUuid\Uuid::uuid7(),
        8 => \FastUuid\Uuid::uuid8(str_repeat("\xab", 16)),
    };
    $w = Minter::wrapCore($core);
    $ok = $ok && get_class($w) === "FastUuid\\Compat\\Rfc4122\\UuidV$v" && $w->getCore() === $core;
}
var_dump($ok);
var_dump(get_class(Minter::mintV4()) === 'FastUuid\Compat\Rfc4122\UuidV4'
    && get_class(Minter::mintV7()) === 'FastUuid\Compat\Rfc4122\UuidV7'
    && get_class(Minter::mintFromDateTime(new DateTimeImmutable('@0'))) === 'FastUuid\Compat\Rfc4122\UuidV1');

// Every helper attaches a non-default codec: the Guid codec swaps the first
// three fields, so the presented bytes differ from the core's.
$g = new \FastUuid\Compat\Codec\GuidStringCodec();
$ns = \FastUuid\Uuid::NAMESPACE_DNS;
$made = [
    Minter::mintV1(null, null, $g),
    Minter::mintV2(0, 1, null, null, $g),
    Minter::mintV3($ns, 'a', $g),
    Minter::mintV4($g),
    Minter::mintV5($ns, 'a', $g),
    Minter::mintV6(null, null, $g),
    Minter::mintV7(null, $g),
    Minter::mintV8(hex2bin('000102030405060708090a0b0c0d0e0f'), $g),
    Minter::mintFromDateTime(new DateTimeImmutable('@0'), null, null, $g),
    Minter::wrapCore(\FastUuid\Uuid::uuid4(), $g),
];
$ok = true;
foreach ($made as $u) {
    $ok = $ok && $u->getBytes() !== $u->getCore()->getBytes();
}
var_dump($ok);
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
