--TEST--
compat: open subclasses keep master behaviour (static stripWrappers, redeclared constants, getCore(): never)
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\AbstractUuid;
use FastUuid\Compat\Codec\StringCodec;
use FastUuid\Compat\Codec\TimestampFirstCombCodec;
use FastUuid\Compat\Guid\Guid;
use FastUuid\Compat\Internal\ConstructionToken;
use FastUuid\Compat\Uuid;
use FastUuid\Compat\UuidFactory;
use FastUuid\Compat\Validator\GenericValidator;
use FastUuid\Exception\InvalidArgumentException;

// A subclass reaching the parent's protected helper with a non-forwarding
// static call: static::class is GenericValidator there, and the helper must
// still unwrap a 36-byte braced input.
final class StaticStripValidator extends GenericValidator
{
    public static function strip(string $uuid): ?string
    {
        return GenericValidator::stripWrappers($uuid);
    }

    public static function stripForwarding(string $uuid): ?string
    {
        return parent::stripWrappers($uuid);
    }
}

$inner34 = str_repeat('a', 34);
$canonical = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
var_dump(StaticStripValidator::strip('{' . $inner34 . '}') === $inner34);
var_dump(StaticStripValidator::stripForwarding('{' . $inner34 . '}') === $inner34);
var_dump(StaticStripValidator::strip('urn:uuid:' . substr($canonical, 0, 27)) === substr($canonical, 0, 27));
var_dump(StaticStripValidator::strip($canonical) === $canonical);
var_dump(StaticStripValidator::strip('{' . $canonical . '}') === $canonical);
var_dump(StaticStripValidator::strip(str_repeat('a', 48)) === null);

// A user subclass redeclaring a version-looking constant never skips the
// class/version check: construction and unserialize() agree.
final class Bound4 extends AbstractUuid
{
    protected const BOUND_VERSION = 4;
    public const VERSION = 4;
}

$v4core = \FastUuid\Uuid::uuid4();
$constructMessage = null;
try {
    new Bound4($v4core, null, ConstructionToken::Trusted);
} catch (InvalidArgumentException $e) {
    $constructMessage = $e->getMessage();
}
var_dump($constructMessage === 'Bound4 cannot wrap bytes that resolve to FastUuid\Compat\Rfc4122\UuidV4');
try {
    new Bound4($v4core);
    var_dump(false);
} catch (InvalidArgumentException) {
    var_dump(true);
}
$payload = str_replace(
    'O:30:"FastUuid\Compat\Rfc4122\UuidV4"',
    'O:6:"Bound4"',
    serialize(Uuid::fromString($v4core->toString())),
);
$restoreMessage = null;
try {
    unserialize($payload);
} catch (InvalidArgumentException $e) {
    $restoreMessage = $e->getMessage();
}
var_dump($restoreMessage === $constructMessage);

// A covariant `never` getCore(): resolution falls back to the string form
// exactly as for a foreign class, whether the subject is the namespace, a
// codec argument, a Guid inner, or an equals()/compareTo() operand.
final class NeverCoreUuid extends AbstractUuid
{
    public function __construct(private string $text, ?\FastUuid\Compat\Codec\CodecInterface $codec = null)
    {
        $this->core = \FastUuid\Uuid::fromString('00000000-0000-4000-8000-000000000000');
        $this->codec = $codec;
    }

    public function getCore(): never
    {
        throw new LogicException('never-returning getCore() must not be called');
    }

    public function toString(): string
    {
        return $this->text;
    }
}

$dns = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
$never = new NeverCoreUuid($dns);
var_dump(Uuid::uuid5($never, 'x')->toString() === \FastUuid\Uuid::uuid5($dns, 'x')->toString());
var_dump(Uuid::uuid3($never, 'x')->toString() === \FastUuid\Uuid::uuid3($dns, 'x')->toString());
$comb = new UuidFactory();
$comb->setCodec(new TimestampFirstCombCodec());
var_dump($comb->uuid5($never, 'x')->getCore()->equals(\FastUuid\Uuid::uuid5($dns, 'x')));
var_dump((new StringCodec())->encodeBinary($never) === \FastUuid\Uuid::fromString($dns)->getBytes());
var_dump((new Guid($never))->getCore()->toString() === $dns);

// With a presentation codec on either side, comparison resolves the operand
// through the string form, as on master.
$combPlain = $comb->wrap(\FastUuid\Uuid::fromString($dns));
$neverComb = new NeverCoreUuid($dns, new TimestampFirstCombCodec());
var_dump($combPlain->equals($never));
var_dump($combPlain->compareTo($never) === 0);
var_dump(Uuid::fromString($dns)->equals($neverComb));
var_dump(Uuid::fromString($dns)->compareTo($neverComb) === 0);

// With no codec on either side master read the operand's getCore() directly;
// that path is unchanged, so the `never` accessor runs.
try {
    Uuid::fromString($dns)->equals($never);
    var_dump(false);
} catch (LogicException) {
    var_dump(true);
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
