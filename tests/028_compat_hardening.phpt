--TEST--
compat: factory getters don't flip the fast path, unserialize validates version, Fields rejects bad length
--EXTENSIONS--
fast_uuid
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/_autoload.inc';

use FastUuid\Uuid;
use FastUuid\Compat\UuidFactory;
use FastUuid\Compat\Provider\NodeProviderInterface;
use FastUuid\Compat\Rfc4122\Fields;
use FastUuid\Exception\InvalidArgumentException as IAE;

function throws(callable $fn, string $class = IAE::class): bool {
    try { $fn(); return false; } catch (\Throwable $e) { return $e instanceof $class; }
}

// --- CR-007: inspecting a provider must NOT register it as custom -----------
$f = new UuidFactory();
$f->getNodeProvider();   // inspect only
$f->getTimeGenerator();
$f->getRandomGenerator();
$rc = new ReflectionObject($f);
$flag = function (string $p) use ($rc, $f) { $x = $rc->getProperty($p); $x->setAccessible(true); return $x->getValue($f); };
var_dump($flag('customNodeProvider') === false);
var_dump($flag('customTimeGenerator') === false);
var_dump($flag('customRandomGenerator') === false);

// a genuinely custom provider still wins (routes off the C fast path)
$f2 = new UuidFactory();
$f2->setNodeProvider(new class implements NodeProviderInterface {
    public function getNode(): string { return hex2bin('aabbccddeeff'); }
});
var_dump(substr(bin2hex($f2->uuid1()->getBytes()), 20) === 'aabbccddeeff');

// --- CR-008: unserialize validates wrapper class against the bytes ----------
$factory = new UuidFactory();
$v4 = $factory->uuid4();
$ser = serialize($v4);
var_dump(get_class(unserialize($ser)) === \FastUuid\Compat\Rfc4122\UuidV4::class); // clean round-trip
$tampered = str_replace($v4->getBytes(), Uuid::uuid1()->getBytes(), $ser);         // v4 wrapper, v1 bytes
var_dump(throws(fn() => unserialize($tampered)));
// nil/max round-trip cleanly under the same check
var_dump(get_class(unserialize(serialize($factory->fromString(Uuid::NIL)))) === \FastUuid\Compat\Rfc4122\NilUuid::class);

// --- CR-009: Fields requires exactly 16 bytes -------------------------------
var_dump(throws(fn() => new Fields('')));
var_dump(throws(fn() => new Fields('short')));
var_dump((new Fields(Uuid::uuid4()->getBytes()))->getVariant() === 2); // 16 bytes ok
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
