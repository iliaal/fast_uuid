--TEST--
Codex review fixes: exception parent, DateTime micros guard, strict fromHexadecimal, validator length cap, value-object parity, v1<->v6
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Uuid as CoreUuid;
use FastUuid\Compat\Uuid as CompatUuid;
use FastUuid\Compat\UuidFactory;
use FastUuid\Compat\Codec\GuidStringCodec;
use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Compat\Type\Integer as IntegerObject;
use FastUuid\Compat\Rfc4122\UuidV1;
use FastUuid\Compat\Rfc4122\UuidV6;
use FastUuid\Compat\Validator\GenericValidator;
use FastUuid\Compat\Validator\NonstandardValidator;
use FastUuid\Exception\InvalidArgumentException;
use FastUuid\Exception\UnsupportedOperationException;

function throws(callable $fn, string $class): bool {
    try { $fn(); return false; } catch (Throwable $e) { return $e instanceof $class; }
}

// CR-004: UnsupportedOperationException extends LogicException (ramsey parity).
var_dump(is_a(UnsupportedOperationException::class, LogicException::class, true));
var_dump(throws(fn() => CoreUuid::uuid4()->getDateTime(), LogicException::class));

// CR-001: a DateTime subclass lying in format('u') is rejected, not silently shifted.
$lie = new class('@1700000000') extends DateTimeImmutable {
    public function format(string $f): string { return $f === 'u' ? '1000000' : parent::format($f); }
};
$neg = new class('@1700000000') extends DateTimeImmutable {
    public function format(string $f): string { return $f === 'u' ? '-1' : parent::format($f); }
};
var_dump(throws(fn() => CoreUuid::uuid7($lie), InvalidArgumentException::class));
var_dump(throws(fn() => CoreUuid::uuid7($neg), InvalidArgumentException::class));
// Non-six-digit format("u") (coerces in-range under zval_get_long) is also rejected.
foreach (['abc', '1', '1e2', '12345', '1234567'] as $bad) {
    $sub = new class('@1700000000') extends DateTimeImmutable {
        public string $u = '';
        public function format(string $f): string { return $f === 'u' ? $this->u : parent::format($f); }
    };
    $sub->u = $bad;
    var_dump(throws(fn() => CoreUuid::uuid7($sub), InvalidArgumentException::class));
}
// A well-behaved subclass still works.
$ok = new class('@1700000000.123456') extends DateTimeImmutable {};
var_dump(CoreUuid::uuid7($ok)->getVersion() === 7);

// CR-005: compat fromHexadecimal is strict (32 hex only), unlike fromString.
$fixed = CompatUuid::fromString('00112233-4455-4677-8899-aabbccddeeff');
$f = new UuidFactory();
var_dump($f->fromHexadecimal($fixed->getHex())->equals($fixed));
var_dump(throws(fn() => $f->fromHexadecimal('00112233-4455-4677-8899-aabbccddeeff'), InvalidArgumentException::class));
var_dump(throws(fn() => $f->fromHexadecimal('urn:uuid:00112233-4455-4677-8899-aabbccddeeff'), InvalidArgumentException::class));
var_dump(throws(fn() => $f->fromHexadecimal('{00112233-4455-4677-8899-aabbccddeeff}'), InvalidArgumentException::class));
// Guid codec must not corrupt network identity (toString-only checks are false-green).
$gf = new UuidFactory();
$gf->setCodec(new GuidStringCodec());
var_dump($gf->fromHexadecimal($fixed->getHex())->equals($fixed));
var_dump($gf->fromInteger((string) $fixed->getInteger())->equals($fixed));

// CR-006: over-long validator input is rejected without a fatal, valid input still passes.
$canonical = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
var_dump((new GenericValidator())->validate(str_repeat('a', 1000)) === false);
var_dump((new NonstandardValidator())->validate('urn:uuid:' . str_repeat('a', 1000)) === false);
var_dump((new GenericValidator())->validate('urn:uuid:' . $canonical));

// CR-010: value-object parity.
var_dump((new Hexadecimal('0XABCD'))->toString() === 'abcd');
var_dump((new Hexadecimal('0xABCD'))->toString() === 'abcd');
var_dump((new Hexadecimal(new Hexadecimal('ff')))->toString() === 'ff'); // copy/Stringable
var_dump((new IntegerObject('+42'))->toString() === '42');
var_dump((new IntegerObject(42.0))->toString() === '42');
var_dump((new IntegerObject(100.0))->toString() === '100');
var_dump(throws(fn() => new IntegerObject(42.5), InvalidArgumentException::class));
var_dump(unserialize(serialize(new IntegerObject('123')))->toString() === '123');
var_dump(unserialize(serialize(new Hexadecimal('dead')))->toString() === 'dead');
// Copy construction under strict_types (declare above) must not throw.
var_dump((new IntegerObject(new IntegerObject('42')))->toString() === '42');
// __unserialize revalidates: a tampered payload is rejected, not accepted as-is.
var_dump(throws(fn() => (new Hexadecimal('a'))->__unserialize(['string' => 'zz']), InvalidArgumentException::class));
var_dump(throws(fn() => (new IntegerObject('0'))->__unserialize(['string' => 'nope']), InvalidArgumentException::class));

// CR-012: constants and v1<->v6 conversions.
var_dump(CompatUuid::RFC_4122 === 2 && CompatUuid::UUID_TYPE_TIME === 1 && CompatUuid::UUID_TYPE_REORDERED_TIME === 6);
$v2 = CompatUuid::uuid2(CompatUuid::DCE_DOMAIN_GROUP, 1);
var_dump($v2->getLocalDomainName() === 'group');
$v1 = CompatUuid::uuid1('010203040506');
var_dump($v1 instanceof UuidV1);
$v6 = UuidV6::fromUuidV1($v1);
var_dump($v6->getVersion() === 6);
var_dump($v6->toUuidV1()->equals($v1)); // round-trips
if (PHP_INT_SIZE >= 8) {
    var_dump($v6->getCore()->getTimestampMillis() === $v1->getCore()->getTimestampMillis());
} else {
    // 32-bit: a current-era ms timestamp exceeds PHP_INT_MAX, so getTimestampMillis throws.
    var_dump(throws(fn() => $v1->getCore()->getTimestampMillis(), UnsupportedOperationException::class));
}
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
