--TEST--
compat: version dispatch, VERSION_CLASSES and CLASS_VERSIONS agree; the constructor fast path never admits nil/max/nonstandard
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Codec\TimestampFirstCombCodec;
use FastUuid\Compat\Internal\ConstructionToken;
use FastUuid\Compat\Internal\WrapperClass;
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
use FastUuid\Compat\Uuid;
use FastUuid\Compat\UuidFactory;
use FastUuid\Exception\InvalidArgumentException;

$versioned = [
    1 => [UuidV1::class, \FastUuid\Uuid::uuid1()],
    2 => [UuidV2::class, \FastUuid\Uuid::uuid2(\FastUuid\Uuid::DCE_DOMAIN_PERSON, 1)],
    3 => [UuidV3::class, \FastUuid\Uuid::uuid3(\FastUuid\Uuid::NAMESPACE_DNS, 'a')],
    4 => [UuidV4::class, \FastUuid\Uuid::uuid4()],
    5 => [UuidV5::class, \FastUuid\Uuid::uuid5(\FastUuid\Uuid::NAMESPACE_DNS, 'a')],
    6 => [UuidV6::class, \FastUuid\Uuid::uuid6()],
    7 => [UuidV7::class, \FastUuid\Uuid::uuid7()],
    8 => [UuidV8::class, \FastUuid\Uuid::uuid8(str_repeat("\1", 16))],
];

$plain = new UuidFactory();
$comb = new UuidFactory();
$comb->setCodec(new TimestampFirstCombCodec());

foreach ($versioned as $version => [$class, $core]) {
    var_dump(WrapperClass::VERSION_CLASSES[$version] === $class
        && WrapperClass::CLASS_VERSIONS[$class] === $version
        && isset(WrapperClass::FINAL_WRAPPERS[$class]));
    var_dump(WrapperClass::for($core) === $class);
    var_dump($core->getVersion() === $version);
    var_dump(get_class(WrapperClass::instantiateMapped($core)) === $class);
    var_dump(get_class($plain->wrap($core)) === $class);
    var_dump(get_class($plain->fromString($core->toString())) === $class);
    var_dump(get_class($plain->fromBytes($core->getBytes())) === $class);
    var_dump(get_class(Uuid::fromString($core->toString())) === $class);
    var_dump(get_class(Uuid::fromBytes($core->getBytes())) === $class);
    var_dump(get_class(new $class($core)) === $class);
    var_dump(get_class(new $class($core, null, ConstructionToken::Trusted)) === $class);
    var_dump($comb->wrap($core)->getCore()->equals($core));
}

// Cores the version fast path must never admit, whatever the class.
$others = [
    'nil' => [NilUuid::class, \FastUuid\Uuid::fromString(\FastUuid\Uuid::NIL)],
    'max' => [MaxUuid::class, \FastUuid\Uuid::fromString(\FastUuid\Uuid::MAX)],
    'v0' => [NonstandardUuid::class, \FastUuid\Uuid::fromString('00000000-0000-0000-8000-000000000000')],
    'v15' => [NonstandardUuid::class, \FastUuid\Uuid::fromString('00000000-0000-f000-8000-000000000000')],
    'ms' => [NonstandardUuid::class, \FastUuid\Uuid::fromString('00112233-4455-4677-c899-aabbccddeeff')],
    'ncs' => [NonstandardUuid::class, \FastUuid\Uuid::fromString('00112233-4455-4677-0899-aabbccddeeff')],
];

$classes = array_map(static fn(array $e): string => $e[0], $versioned + $others);
$cores = array_map(static fn(array $e): \FastUuid\Uuid => $e[1], $versioned + $others);

function accepts(string $class, \FastUuid\Uuid $core): bool
{
    try {
        new $class($core, null, ConstructionToken::Trusted);
        return true;
    } catch (InvalidArgumentException) {
        return false;
    }
}

foreach ($others as $key => [$class, $core]) {
    var_dump(WrapperClass::for($core) === $class);
    var_dump(get_class(WrapperClass::instantiateMapped($core)) === $class);
    var_dump(get_class($plain->fromBytes($core->getBytes())) === $class);
}

// Full matrix: a wrapper accepts a core exactly when for() names it.
$exact = true;
foreach (array_unique($classes) as $class) {
    foreach ($cores as $core) {
        $exact = $exact && (accepts($class, $core) === (WrapperClass::for($core) === $class));
    }
}
var_dump($exact);

// An anonymous subclass is not in VERSION_CLASSES, so it cannot wrap a core
// that resolves to an in-tree class.
try {
    new class(\FastUuid\Uuid::uuid4()) extends \FastUuid\Compat\AbstractUuid {};
    var_dump(false);
} catch (InvalidArgumentException) {
    var_dump(true);
}

// FINAL_WRAPPERS is exactly the set of in-tree AbstractUuid subclasses; each is
// final (InlinedToString would otherwise skip a subclass's toString()) and
// inherits getCore()/toString() from AbstractUuid.
$src = realpath(__DIR__ . '/../compat/src');
$subs = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)) as $f) {
    if ($f->getExtension() !== 'php') continue;
    $cls = 'FastUuid\\Compat\\' . str_replace('/', '\\', substr($f->getPathname(), strlen($src) + 1, -4));
    if (class_exists($cls) && is_subclass_of($cls, \FastUuid\Compat\AbstractUuid::class)) $subs[] = $cls;
}
$fw = array_keys(\FastUuid\Compat\Internal\WrapperClass::FINAL_WRAPPERS);
sort($subs); sort($fw);
var_dump($subs === $fw);
$ok = true;
foreach ($fw as $cls) {
    $r = new ReflectionClass($cls);
    $ok = $ok && $r->isFinal()
        && $r->getMethod('getCore')->class === \FastUuid\Compat\AbstractUuid::class
        && $r->getMethod('toString')->class === \FastUuid\Compat\AbstractUuid::class;
}
var_dump($ok && count($fw) > 0);

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
