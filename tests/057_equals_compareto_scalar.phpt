--TEST--
equals/compareTo scalar contract on core and compat layers
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Codec\GuidStringCodec;
use FastUuid\Compat\Uuid as CompatUuid;
use FastUuid\Compat\UuidFactory;
use FastUuid\Exception\InvalidArgumentException as IAE;
use FastUuid\Uuid as CoreUuid;

function throwsIae(callable $fn): bool {
    try {
        $fn();
        return false;
    } catch (IAE) {
        return true;
    }
}

$s = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
$core = CoreUuid::fromString($s);
$compat = CompatUuid::fromString($s);

var_dump($core->equals(null) === false);
var_dump($core->equals(123) === false);
var_dump($core->equals(true) === false);
var_dump($core->equals('garbage') === false);
var_dump($core->equals('') === false);
var_dump($core->equals($s));

var_dump(throwsIae(fn() => $core->compareTo(null)));
var_dump(throwsIae(fn() => $core->compareTo(42)));
var_dump(throwsIae(fn() => $core->compareTo(false)));
var_dump(throwsIae(fn() => $core->compareTo('garbage')));
var_dump(throwsIae(fn() => $core->compareTo('')));
var_dump($core->compareTo($s) === 0);

var_dump($compat->equals(null) === false);
var_dump($compat->equals(0) === false);
var_dump($compat->equals(42) === false);
var_dump($compat->equals(true) === false);
var_dump($compat->equals(false) === false);
var_dump($compat->equals($s));
var_dump($compat->equals('garbage') === false);
var_dump($compat->equals('') === false);
var_dump($compat->equals(new stdClass()) === false);
$same = new class($s) implements Stringable {
    public function __construct(private string $v) {}
    public function __toString(): string { return $this->v; }
};
var_dump($compat->equals($same));
$junk = new class() implements Stringable {
    public function __toString(): string { return 'not-a-uuid'; }
};
var_dump($compat->equals($junk) === false);

var_dump(throwsIae(fn() => $compat->compareTo(null)));
var_dump(throwsIae(fn() => $compat->compareTo(0)));
var_dump(throwsIae(fn() => $compat->compareTo(true)));
var_dump(throwsIae(fn() => $compat->compareTo('garbage')));
var_dump(throwsIae(fn() => $compat->compareTo('')));
var_dump(throwsIae(fn() => $compat->compareTo($s)));
// Object comparison is network-byte-ordered and codec-independent.
$other = CompatUuid::uuid4();
var_dump($compat->compareTo($compat) === 0);
var_dump($compat->compareTo($core) === 0);
var_dump(($compat->compareTo($other) <=> 0) === ($core->compareTo($other->getCore()) <=> 0));
$factory = new UuidFactory();
$factory->setCodec(new GuidStringCodec());
$ca = $factory->fromString($s);
$cb = $factory->fromString($other->toString());
var_dump(($ca->compareTo($cb) <=> 0) === ($core->compareTo($other->getCore()) <=> 0));
var_dump(throwsIae(fn() => $ca->compareTo(null)));
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
